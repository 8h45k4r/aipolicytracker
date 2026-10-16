@component('emails.site.layout', ['title' => 'Licence enquiry', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Licence enquiry</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">{{ $enquiry->tierLabel() }} · {{ $enquiry->organization }}</h1>
<p style="margin:0 0 8px;font-size:13px;color:#5D6B7E;">From {{ $enquiry->name }} &lt;{{ $enquiry->email }}&gt;, sent from /{{ $enquiry->source ?? 'pricing' }}. Reply to this email to answer them.</p>
<p style="margin:12px 0 4px;"><strong>What they will use the data for</strong></p>
<p style="margin:0 0 8px;white-space:pre-line;">{{ \Illuminate\Support\Str::limit($enquiry->use_case, 3000) }}</p>
@if($enquiry->coverage_request)<p style="margin:12px 0 4px;"><strong>Coverage requested</strong></p>
<p style="margin:0 0 8px;white-space:pre-line;">{{ \Illuminate\Support\Str::limit($enquiry->coverage_request, 2000) }}</p>@endif
<p style="margin:16px 0 0;"><a href="{{ $adminUrl }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:600;">Open the enquiries list</a></p>
@endcomponent
