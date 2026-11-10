Licence enquiry: {{ $enquiry->tierLabel() }} · {{ $enquiry->organization }}

From: {{ $enquiry->name }} <{{ $enquiry->email }}> (sent from /{{ $enquiry->source ?? 'pricing' }})

What they will use the data for:
{{ \Illuminate\Support\Str::limit($enquiry->use_case, 3000) }}
@if($enquiry->coverage_request)

Coverage requested:
{{ \Illuminate\Support\Str::limit($enquiry->coverage_request, 2000) }}
@endif

All enquiries: {{ $adminUrl }}
