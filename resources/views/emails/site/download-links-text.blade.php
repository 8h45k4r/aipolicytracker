{{ $tool->title }} (version {{ $tool->version }})

Your download links (valid 24 hours, personal to your account):
@foreach($links as $l)- {{ $l['label'] }}: {{ $l['url'] }}
@endforeach
@if($guide)Related guide: {{ route('guides.show', $guide['slug']) }}@endif
@if($next)Next step: {{ $next->url() }}@endif

Informational only, not legal advice.
