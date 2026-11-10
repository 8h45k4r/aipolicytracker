<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\LicenceEnquiryMail;
use App\Models\LicenceEnquiry;
use App\Rules\NotDisposableEmail;
use App\Rules\WorkEmail;
use App\Services\Security\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * The licence and custom-coverage enquiry form on /pricing and /open-data. The row is
 * written first and the contact address told second, so the page confirms only what
 * was stored, and says "sent" only when the mail went.
 */
class LicenceEnquiryController extends Controller
{
    /** Past this many enquiries an hour the admin list is the notification, not the inbox. */
    private const MAILS_PER_HOUR = 20;

    public function store(Request $request, Turnstile $turnstile): RedirectResponse
    {
        $source = LicenceEnquiry::SOURCES[(string) $request->input('source')] ?? 'pricing';
        $back = route($source).'#licence';

        // A filled honeypot is a bot. Nothing is stored and nothing is confirmed.
        if (filled($request->input('website'))) {
            return redirect()->to($back);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', new NotDisposableEmail, new WorkEmail('licence enquiries')],
            'organization' => ['required', 'string', 'min:2', 'max:160'],
            'use_case' => ['required', 'string', 'min:20', 'max:3000'],
            'tier' => ['required', 'in:'.implode(',', array_keys(LicenceEnquiry::TIERS))],
            'coverage_request' => ['nullable', 'string', 'max:2000'],
        ], [
            'use_case.required' => 'Tell us what you will use the data for.',
            'use_case.min' => 'Say a little more about what you will use the data for (at least 20 characters).',
        ], [
            'organization' => 'organisation',
            'use_case' => 'use of the data',
            'tier' => 'tier of interest',
            'coverage_request' => 'coverage request',
        ]);
        if ($validator->fails()) {
            return redirect()->to($back)->withErrors($validator, 'licence')->withInput();
        }
        if ($turnstile->rejects($request)) {
            return redirect()->to($back)->withErrors(['turnstile' => 'The automated check did not complete. Please wait a moment and submit again.'], 'licence')->withInput();
        }
        $data = $validator->validated();

        $enquiry = LicenceEnquiry::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'organization' => trim($data['organization']),
            'tier' => $data['tier'],
            'use_case' => trim($data['use_case']),
            'coverage_request' => filled($data['coverage_request'] ?? null) ? trim($data['coverage_request']) : null,
            'source' => $source,
            'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
        ]);

        $sent = false;
        $recent = LicenceEnquiry::where('created_at', '>=', now()->subHour())->count();
        foreach ($recent <= self::MAILS_PER_HOUR ? (array) config('aipolicytracker.contact_emails', []) : [] as $address) {
            try {
                Mail::to($address)->send(new LicenceEnquiryMail($enquiry));
                $sent = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($sent) {
            $enquiry->forceFill(['notified_at' => now()])->save();
        }

        return redirect()->to($back)->with('licence_enquiry', $sent
            ? 'Thank you. Your enquiry is recorded and has been sent to the maintainer, who will reply to '.$enquiry->email.'.'
            : 'Thank you. Your enquiry is recorded, and the maintainer will reply to '.$enquiry->email.'.');
    }
}
