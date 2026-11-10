@component('emails.site.layout', ['message' => $message ?? null, 'title' => 'New template version', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#006AAC;font-weight:600;">Template update</p>
<h1 style="font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">{{ $title }}: {{ $label }}</h1>
<p style="margin:0 0 16px;">A new version of this template was built{{ $date ? ' on '.$date : '' }}, because the records it is made from changed. You asked to be told when that happens.</p>
@if($changelog !== '')
<p style="margin:0 0 4px;font-weight:600;color:#002147;">What changed</p>
<p style="margin:0 0 16px;white-space:pre-line;">{{ $changelog }}</p>
@endif
<p style="margin:0 0 16px;"><a href="{{ $pageUrl }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:10px 18px;font-weight:600;">Get {{ $label }}</a></p>
@if($related !== [])
<p style="margin:16px 0 4px;font-weight:600;color:#002147;">Related templates</p>
<ul style="margin:0 0 16px;padding-left:20px;">
@foreach($related as $r)<li style="margin:0 0 4px;"><a href="{{ $r['url'] }}" style="color:#006AAC;">{{ $r['title'] }}</a></li>@endforeach
</ul>
@endif
<p style="margin:16px 0 0;font-size:13px;color:#5D6B7E;">You receive this because you ticked "email me when this template gets a new version" when you requested it. <a href="{{ $unsubscribeUrl }}" style="color:#006AAC;">Stop template update emails</a>. This does not subscribe you to anything else. Informational only, not legal advice.</p>
@endcomponent
