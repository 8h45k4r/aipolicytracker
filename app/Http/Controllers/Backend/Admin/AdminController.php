<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionConfirmMail;
use App\Models\AppSetting;
use App\Models\ChangeEvent;
use App\Models\ContributorSubmission;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\Subscriber;
use App\Services\ExternalData\ExternalDataset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function dashboard(ExternalDataset $external): View
    {
        $stats = [
            'jurisdictions' => Jurisdiction::published()->count(),
            'policies' => PolicyInstrument::published()->count(),
            'policies_verified' => PolicyInstrument::published()->where('review_status', 'verified')->count(),
            'obligations' => Obligation::published()->count(),
            'changes_30d' => ChangeEvent::published()->where('occurred_on', '>=', now()->subDays(30)->toDateString())->count(),
            'submissions_pending' => ContributorSubmission::where('status', 'pending_review')->count(),
            'subscribers_active' => Subscriber::active()->count(),
            'subscribers_unconfirmed' => Subscriber::whereNull('confirmed_at')->whereNull('unsubscribed_at')->count(),
        ];
        $stale = PolicyInstrument::published()->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays(180)))->count();
        $recentSubmissions = ContributorSubmission::orderByDesc('created_at')->limit(5)->get();
        $mail = ['mailer' => config('mail.default'), 'from' => config('mail.from.address'), 'resend_key_set' => (bool) config('services.resend.key')];
        $aiid = $external->aiid();

        return view('backend.admin.dashboard', compact('stats', 'stale', 'recentSubmissions', 'mail', 'aiid'));
    }

    public function submissions(Request $request): View
    {
        $type = $request->query('type');
        $status = $request->query('status');
        $q = ContributorSubmission::with('decisions.reviewer')->orderByDesc('created_at');
        if ($type && array_key_exists($type, ContributorSubmission::TYPES)) {
            $q->where('type', $type);
        }
        if ($status) {
            $q->where('status', $status);
        }
        $submissions = $q->paginate(25)->withQueryString();
        $byType = ContributorSubmission::selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
        $byStatus = ContributorSubmission::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('backend.admin.submissions', compact('submissions', 'byType', 'byStatus', 'type', 'status'));
    }

    public function subscribers(Request $request): View
    {
        $state = $request->query('state', 'active');
        $q = Subscriber::orderByDesc('created_at');
        match ($state) {
            'unconfirmed' => $q->whereNull('confirmed_at')->whereNull('unsubscribed_at'),
            'unsubscribed' => $q->whereNotNull('unsubscribed_at'),
            'all' => $q,
            default => $q->active(),
        };
        $subscribers = $q->paginate(50)->withQueryString();
        $counts = ['active' => Subscriber::active()->count(), 'unconfirmed' => Subscriber::whereNull('confirmed_at')->whereNull('unsubscribed_at')->count(), 'unsubscribed' => Subscriber::whereNotNull('unsubscribed_at')->count(), 'all' => Subscriber::count()];

        return view('backend.admin.subscribers', compact('subscribers', 'counts', 'state'));
    }

    public function subscribersExport(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'topics', 'confirmed_at', 'unsubscribed_at', 'last_sent_at', 'source', 'created_at']);
            Subscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [$s->email, implode('|', $s->topics ?? []), $s->confirmed_at, $s->unsubscribed_at, $s->last_sent_at, $s->source, $s->created_at]);
                }
            });
            fclose($out);
        }, 'subscribers-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function subscriberResend(Subscriber $subscriber): RedirectResponse
    {
        Mail::to($subscriber->email)->send(new SubscriptionConfirmMail($subscriber));

        return back()->with('success', "Confirmation email re-sent to {$subscriber->email}.");
    }

    public function subscriberDelete(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    public function external(ExternalDataset $external): View
    {
        return view('backend.admin.external', ['aiid' => $external->aiid(), 'mit' => $external->mitRisk()]);
    }

    public function settings(): View
    {
        $values = [];
        foreach (AppSetting::KEYS as $key => $meta) {
            $current = AppSetting::get($key);
            $values[$key] = ['meta' => $meta, 'set' => $current !== null && $current !== '', 'display' => $meta['secret'] ? AppSetting::mask($current) : ($current ?? ''), 'env' => match ($key) {
                'mail_mailer' => env('MAIL_MAILER'), 'resend_key' => env('RESEND_KEY') ? 'set in environment' : null, 'mail_from_address' => env('MAIL_FROM_ADDRESS'), 'mail_from_name' => env('MAIL_FROM_NAME'), 'cron_token' => env('CRON_TOKEN') ? 'set in environment' : null, default => null,
            }];
        }

        return view('backend.admin.settings', ['values' => $values, 'effective' => ['mailer' => config('mail.default'), 'from' => config('mail.from.address').' ('.config('mail.from.name').')', 'resend' => (bool) config('services.resend.key')]]);
    }

    public function settingsSave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => ['nullable', 'in:log,resend,smtp,array'],
            'resend_key' => ['nullable', 'string', 'max:200', 'regex:/^re_[A-Za-z0-9_]+$/'],
            'mail_from_address' => ['nullable', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'cron_token' => ['nullable', 'string', 'min:24', 'max:128'],
            'clear' => ['nullable', 'array'],
            'clear.*' => ['in:'.implode(',', array_keys(AppSetting::KEYS))],
        ]);
        foreach (AppSetting::KEYS as $key => $meta) {
            if (in_array($key, $data['clear'] ?? [], true)) {
                AppSetting::put($key, null, $request->user()->id);

                continue;
            }
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                AppSetting::put($key, $data[$key], $request->user()->id);
            }
        }

        return back()->with('success', 'Settings saved. Secrets are stored encrypted and only shown masked.');
    }

    public function settingsTestMail(Request $request): RedirectResponse
    {
        $to = $request->validate(['to' => ['required', 'email']])['to'];
        try {
            Mail::raw('AIPolicyTracker test message sent '.now()->toDateTimeString().' via '.config('mail.default').'.', fn ($m) => $m->to($to)->subject('AIPolicyTracker mail test'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['to' => 'Send failed: '.mb_substr($e->getMessage(), 0, 200)]);
        }

        return back()->with('success', "Test message sent to {$to} via ".config('mail.default').'.');
    }
}
