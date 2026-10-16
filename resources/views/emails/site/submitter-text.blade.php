{{ $heading }}

{{ $summary }}
@if($received)

A reviewer checks it against official sources before anything is published. We will email you when a decision is made, and a reviewer may write to you with a question first.
@else

The decision is recorded in the public corrections log: {{ $corrections }}
@endif
@if($publicNote)

{{ $publicNote }}
@endif

Send another submission: {{ $contributeUrl }}
