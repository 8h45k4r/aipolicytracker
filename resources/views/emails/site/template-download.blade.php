@component('emails.site.layout', ['title' => 'Your template download', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">AI governance template</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">{{ $title }} ({{ $label }})</h1>
<p style="margin:0 0 16px;">Hello {{ $name }}, thank you for your request. Your files are ready. The links below are personal to this request and work for {{ $days }} days; after that, request the template again from <a href="{{ $pageUrl }}" style="color:#006AAC;">its page</a>.</p>
@foreach($links as $l)<p style="margin:0 0 8px;"><a href="{{ $l['url'] }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:10px 18px;font-weight:600;">Download {{ $l['label'] }}</a> <span style="font-size:13px;color:#5D6B7E;">{{ $l['size'] }}</span></p>@endforeach
<p style="margin:16px 0 0;font-size:13px;color:#5D6B7E;">Each file states its version, the date it was built and the dataset it was built from, and links every duty to its record. It is informational only, not legal advice: completing a template does not make an organisation compliant with any law or standard. Licensed CC BY 4.0.</p>
@endcomponent
