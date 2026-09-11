@component('emails.site.layout', ['title' => 'New submission', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Admin notification</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">New {{ strtolower(\App\Models\ContributorSubmission::TYPES[$submission->type] ?? $submission->type) }}</h1>
<p style="margin:0 0 8px;"><strong>{{ $submission->summary }}</strong></p>
@if($submission->details)<p style="margin:0 0 8px;white-space:pre-line;">{{ \Illuminate\Support\Str::limit($submission->details, 1200) }}</p>@endif
@if($submission->proposed_source_url)<p style="margin:0 0 8px;">Proposed source: <a href="{{ $submission->proposed_source_url }}" style="color:#006AAC;">{{ $submission->proposed_source_url }}</a></p>@endif
<p style="margin:0 0 16px;font-size:13px;color:#5D6B7E;">From {{ $submission->submitter_name ?: 'anonymous' }}{{ $submission->submitter_email ? ' <'.$submission->submitter_email.'>' : '' }}{{ $submission->submitter_affiliation ? ' · '.$submission->submitter_affiliation : '' }}@if($submission->subject_slug) · about {{ $submission->subject_type }}: {{ $submission->subject_slug }}@endif</p>
<p style="margin:0;"><a href="{{ $adminUrl }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:600;">Open in the review queue</a></p>
@endcomponent
