{{ $name }}: compliance workbook version {{ $versionNumber }}

The records behind this system's workbook changed, so a new version was built. What changed:

@foreach($lines as $l)
- {{ $l['word'] }}: {{ $l['title'] }} ({{ $l['instrument'] }}){{ $l['fields'] ? ' · '.$l['fields'] : '' }}
  {{ $l['url'] }}
@endforeach
@if($more > 0)
And {{ $more }} more, listed in the workbook's Version history sheet.
@endif

Download version {{ $versionNumber }}: {{ $downloadUrl }}
All versions and the system's details: {{ $pageUrl }}

An educational screen over recorded scope, not legal advice: open the official source before acting. You receive this because you saved this system on your account.
Stop alert emails: {{ $unsubscribeUrl }}
