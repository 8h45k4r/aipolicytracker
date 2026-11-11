@component('emails.site.layout', ['message' => $message ?? null, 'title' => 'Compliance workbook updated', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#006AAC;font-weight:600;">Compliance workbook</p>
<h1 style="font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">{{ $name }}: version {{ $versionNumber }}</h1>
<p style="margin:0 0 16px;">The records behind this system's workbook changed, so a new version was built. What changed:</p>
<ul style="margin:0 0 16px;padding-left:20px;">
@foreach($lines as $l)<li style="margin:0 0 6px;">{{ $l['word'] }}: <a href="{{ $l['url'] }}" style="color:#006AAC;">{{ $l['title'] }}</a> ({{ $l['instrument'] }})@if($l['fields']) · {{ $l['fields'] }}@endif</li>@endforeach
</ul>
@if($more > 0)<p style="margin:0 0 16px;">And {{ $more }} more, listed in the workbook's Version history sheet.</p>@endif
<p style="margin:0 0 16px;"><a href="{{ $downloadUrl }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:10px 18px;font-weight:600;">Download version {{ $versionNumber }}</a></p>
<p style="margin:0 0 16px;"><a href="{{ $pageUrl }}" style="color:#006AAC;">All versions and the system's details</a></p>
<p style="margin:16px 0 0;font-size:13px;color:#5D6B7E;">An educational screen over recorded scope, not legal advice: open the official source before acting. You receive this because you saved this system on your account. <a href="{{ $unsubscribeUrl }}" style="color:#006AAC;">Stop alert emails</a>.</p>
@endcomponent
