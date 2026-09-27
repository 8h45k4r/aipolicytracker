{{ $title }} ({{ $label }})

Hello {{ $name }}, thank you for your request. Your files are ready. These links are personal to this request and work for {{ $days }} days; after that, request the template again at {{ $pageUrl }}

@foreach($links as $l)
Download {{ $l['label'] }} ({{ $l['size'] }}): {{ $l['url'] }}
@endforeach

Each file states its version, build date and dataset, and links every duty to its record. Informational only, not legal advice. Licensed CC BY 4.0.
