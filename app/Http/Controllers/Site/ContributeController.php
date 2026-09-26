<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\SubmissionReceivedMail;
use App\Models\ChangeEvent;
use App\Models\ContributorSubmission;
use App\Models\Control;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TransitionMeasure;
use App\Rules\NotDisposableEmail;
use App\Support\PageTitle;
use App\Support\Seo;
use App\Support\SubmissionFieldLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContributeController extends Controller
{
    /** Fields a reader can single out when reporting a correction, per record type. */
    public const CORRECTABLE_FIELDS = [
        'policy' => ['title', 'status', 'instrument_type', 'is_binding', 'issuing_body', 'adopted_on', 'in_force_on', 'applies_from', 'summary_plain', 'scope_summary', 'key_dates_summary', 'penalties_summary', 'who_it_applies_to', 'what_organizations_must_do', 'official_source_url'],
        'jurisdiction' => ['name', 'regulatory_status_summary', 'binding_vs_guidance', 'current_priorities', 'regulators', 'official_source_url'],
        'obligation' => ['title', 'category', 'is_binding', 'summary', 'practical_action', 'applies_from', 'source_reference', 'official_source_url', 'controls'],
        'change' => ['title', 'occurred_on', 'what_changed', 'practical_impact', 'impact_level', 'status_after', 'official_source_url'],
        'control' => ['title', 'kind', 'purpose', 'description', 'owner_role', 'frequency'],
        'incident' => ['title', 'occurred_on', 'description', 'deployers', 'developers', 'harmed', 'mit_domain', 'mit_subdomain', 'entity', 'intent', 'timing', 'harm_level', 'countries'],
        'transition_measure' => ['title', 'measure_type', 'status', 'summary', 'mechanism', 'funding', 'trigger', 'benefit', 'cost', 'bill_number', 'sponsors', 'introduced_on', 'enacted_on', 'in_force_on', 'official_source_url', 'review_status'],
        'risk' => ['risk_category', 'risk_subcategory', 'description', 'domain', 'subdomain', 'entity', 'intent', 'timing', 'paper_title'],
    ];

    public function show(Request $request): View
    {
        $subjectType = $request->query('subject_type');
        $subjectSlug = (string) $request->query('subject_slug', '');
        $subject = $this->resolveSubject($subjectType, $subjectSlug);

        $seo = Seo::make(
            $subject ? 'Report a correction: '.$subject['title'] : 'Contribute: report errors, propose sources, submit policy records',
            'How to report an error, propose an official source, submit a new AI policy record or volunteer as a reviewer. Submissions default to pending review before publication.',
            route('contribute')
        )->withBreadcrumbs([['Home', route('home')], ['Contribute', route('contribute')]])
            ->withPageType('ContactPage');
        if ($subject) {
            $seo->noindex();
        }

        return view('site.pages.contribute', [
            'seo' => $seo,
            'types' => ContributorSubmission::TYPES,
            'subject' => $subject,
            'prefill' => [
                'type' => array_key_exists($request->query('type', ''), ContributorSubmission::TYPES) ? $request->query('type') : 'correction',
                'subject_type' => $subject['type'] ?? (in_array($subjectType, ['policy', 'jurisdiction', 'obligation', 'change', 'control', 'incident', 'risk', 'other'], true) ? $subjectType : null),
                'subject_slug' => $subject['slug'] ?? (preg_match('/^[A-Za-z0-9._-]{0,160}$/', $subjectSlug) ? $subjectSlug : null),
                'field' => $subject && array_key_exists((string) $request->query('field'), $subject['fields']) ? $request->query('field') : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: bots fill the hidden "website" field.
        if ($request->filled('website')) {
            return redirect()->route('contribute')->with('success', 'Thank you. Your submission is pending review.');
        }

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(ContributorSubmission::TYPES))],
            'subject_type' => ['nullable', 'in:policy,jurisdiction,obligation,change,control,incident,risk,other'],
            'subject_slug' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9._-]*$/'],
            'field' => ['nullable', 'string', 'max:64', 'regex:/^[a-z_]*$/'],
            'current_value' => ['nullable', 'string', 'max:4000'],
            'proposed_value' => ['nullable', 'string', 'max:4000'],
            'summary' => ['required', 'string', 'min:10', 'max:300'],
            'details' => ['nullable', 'string', 'max:5000'],
            'proposed_source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'submitter_name' => ['nullable', 'string', 'max:120'],
            'submitter_email' => ['nullable', 'email', 'max:190', new NotDisposableEmail],
            'submitter_affiliation' => ['nullable', 'string', 'max:190'],
            'source_page' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        // Record context is captured at submission time so a reviewer sees exactly what the
        // reader saw, even if the record changes before the queue is worked.
        $subject = $this->resolveSubject($data['subject_type'] ?? null, $data['subject_slug'] ?? '');
        $payload = array_filter([
            'field' => $data['field'] ?? null,
            'current_value' => $data['current_value'] ?? null,
            'proposed_value' => $data['proposed_value'] ?? null,
            'record_title' => $subject['title'] ?? null,
            'record_url' => $subject['url'] ?? null,
            'record_official_source_url' => $subject['official_source_url'] ?? null,
            'record_content_version' => $subject['content_version'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        unset($data['field'], $data['current_value'], $data['proposed_value']);

        $submission = ContributorSubmission::create($data + ['status' => 'pending_review', 'payload' => $payload ?: null]);
        // Every submission is kept, but a flood does not become a flood of mail: past
        // twenty an hour the review queue is the notification.
        $recent = ContributorSubmission::where('created_at', '>=', now()->subHour())->count();
        foreach ($recent <= 20 ? config('aipolicytracker.admin_emails', []) : [] as $admin) {
            try {
                Mail::to($admin)->send(new SubmissionReceivedMail($submission));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('contribute')->with('success', 'Thank you. Your submission has been recorded with status "pending review". A reviewer will check it against official sources before anything is published.');
    }

    /**
     * Load the published record a correction refers to and flatten the fields a reader may
     * dispute into label => current value pairs. Returns null when nothing resolves.
     *
     * @return array{type:string,slug:string,title:string,url:string,official_source_url:?string,source_title:?string,jurisdiction:?string,content_version:?int,fields:array<string,array{label:string,value:?string}>}|null
     */
    public static function resolveSubject(?string $type, string $slug): ?array
    {
        if (! $type || $slug === '' || ! preg_match('/^[A-Za-z0-9._-]{1,160}$/', $slug)) {
            return null;
        }

        $record = match ($type) {
            'policy' => PolicyInstrument::published()->with('jurisdiction')->where('slug', $slug)->first(),
            'jurisdiction' => Jurisdiction::published()->where('slug', $slug)->first(),
            'obligation' => Obligation::published()->with('policyInstrument.jurisdiction')->where('slug', $slug)->first(),
            'change' => ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->where('slug', $slug)->first(),
            'control' => Control::published()->where('slug', $slug)->first(),
            'transition_measure' => TransitionMeasure::whereNotNull('published_at')->with('jurisdiction')->where('slug', $slug)->first(),
            'incident' => ctype_digit($slug) ? ExternalIncident::find((int) $slug) : null,
            'risk' => ExternalRisk::find(str_replace('--', '#', $slug)),
            default => null,
        };
        if (! $record) {
            return null;
        }

        $fields = [];
        foreach (self::CORRECTABLE_FIELDS[$type] ?? [] as $field) {
            // A relation is reported by the slugs it points at, so a reader can say which link is wrong.
            $value = $field === 'controls' ? $record->controls->pluck('slug')->all() : ($record->{$field} ?? null);
            $fields[$field] = ['label' => SubmissionFieldLabels::label($field), 'value' => self::stringify($value)];
        }

        [$title, $url, $jurisdiction] = match ($type) {
            'policy' => [$record->short_title ?: $record->title, $record->url(), $record->jurisdiction?->name],
            'jurisdiction' => [$record->name, $record->url(), null],
            'obligation' => [$record->title, $record->url(), $record->policyInstrument?->jurisdiction?->name],
            'change' => [$record->title, $record->url(), $record->jurisdiction?->name],
            'control' => [$record->title, $record->url(), null],
            'incident' => [PageTitle::incident($record), $record->url(), 'AI Incident Database'],
            'risk' => [PageTitle::risk($record), $record->url(), 'MIT AI Risk Repository'],
        };

        return [
            'type' => $type,
            'slug' => $slug,
            'title' => $title,
            'url' => $url,
            'official_source_url' => match ($type) {
                'incident' => $record->citeUrl(), 'risk' => $record->navigatorUrl(), 'obligation' => $record->official_source_url ?? $record->policyInstrument?->official_source_url, 'control' => null, default => $record->official_source_url
            },
            'source_title' => match ($type) {
                'incident' => 'AI Incident Database record', 'risk' => 'MIT AI Risk Repository (Risk Navigator)', 'obligation' => $record->source_title ?? $record->policyInstrument?->source_title, 'control' => 'Original control record (editorial)', default => $record->source_title
            },
            'jurisdiction' => $jurisdiction,
            'content_version' => isset($record->content_version) ? (int) $record->content_version : null,
            'fields' => $fields,
        ];
    }

    private static function stringify(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Binding' : 'Non-binding';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            $names = array_map(fn ($v) => is_array($v) ? ($v['name'] ?? json_encode($v)) : (string) $v, $value);

            return implode('; ', $names) ?: null;
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
