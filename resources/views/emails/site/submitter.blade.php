@component('emails.site.layout', ['title' => $heading, 'unsubscribeUrl' => $unsubscribeUrl])
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">{{ $heading }}</h1>
<p style="margin:0 0 8px;"><strong>{{ $summary }}</strong></p>
@if($received)
<p style="margin:0 0 8px;">A reviewer checks it against official sources before anything is published. We will email you when a decision is made, and a reviewer may write to you with a question first.</p>
@else
<p style="margin:0 0 8px;">The decision is recorded in the public <a href="{{ $corrections }}" style="color:#006AAC;">corrections log</a>.</p>
@endif
@if($publicNote)<p style="margin:0 0 16px;padding:12px;background:#F4F6F9;white-space:pre-line;">{{ $publicNote }}</p>@endif
<p style="margin:0;font-size:13px;color:#5D6B7E;">Reply to this message only if you want to add something. You can also send another submission at <a href="{{ $contributeUrl }}" style="color:#006AAC;">aipolicytracker.org/contribute</a>.</p>
@endcomponent
