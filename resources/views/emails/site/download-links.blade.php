@component('emails.site.layout', ['title' => 'Your download links', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Free tool</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">{{ $tool->title }} (version {{ $tool->version }})</h1>
<p style="margin:0 0 16px;">Thanks for downloading. The links below are personal to your account and work for 24 hours; after that, open the tool page while signed in to get fresh ones.</p>
@foreach($links as $l)<p style="margin:0 0 8px;"><a href="{{ $l['url'] }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:10px 18px;font-weight:600;">Download {{ $l['label'] }}</a></p>@endforeach
@if($guide)<p style="margin:16px 0 0;">Read the related guide: <a href="{{ route('guides.show', $guide['slug']) }}" style="color:#006AAC;">{{ $guide['h1'] }}</a></p>@endif
@if($next)<p style="margin:8px 0 0;">Next step: <a href="{{ $next->url() }}" style="color:#006AAC;">{{ $next->title }}</a></p>@endif
<p style="margin:16px 0 0;font-size:13px;color:#5D6B7E;">Every file states its version and date and is informational only, not legal advice. Completing a template does not make an organisation compliant with any law or standard.</p>
@endcomponent
