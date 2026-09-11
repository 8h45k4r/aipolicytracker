New submission: {{ \App\Models\ContributorSubmission::TYPES[$submission->type] ?? $submission->type }}

{{ $submission->summary }}
@if($submission->details){{ \Illuminate\Support\Str::limit($submission->details, 1200) }}@endif
@if(!empty($submission->payload['field']))Field: {{ $submission->payload['field'] }} | Shown: {{ $submission->payload['current_value'] ?? '—' }} | Proposed: {{ $submission->payload['proposed_value'] ?? '—' }}@endif
@if(!empty($submission->payload['record_url']))Record: {{ $submission->payload['record_url'] }}@endif
@if($submission->proposed_source_url)Proposed source: {{ $submission->proposed_source_url }}@endif
From: {{ $submission->submitter_name ?: 'anonymous' }} {{ $submission->submitter_email }}

Review: {{ $adminUrl }}
