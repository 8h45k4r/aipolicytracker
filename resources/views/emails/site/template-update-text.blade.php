{{ $title }}: {{ $label }}

A new version of this template was built{{ $date ? ' on '.$date : '' }}, because the records it is made from changed. You asked to be told when that happens.
@if($changelog !== '')

What changed
{{ $changelog }}
@endif

Get {{ $label }}: {{ $pageUrl }}
@if($related !== [])

Related templates
@foreach($related as $r)
- {{ $r['title'] }}: {{ $r['url'] }}
@endforeach
@endif

You receive this because you ticked "email me when this template gets a new version" when you requested it. This does not subscribe you to anything else.
Stop template update emails: {{ $unsubscribeUrl }}
