New submission: {{ \App\Models\ContributorSubmission::TYPES[$submission->type] ?? $submission->type }}

{{ $submission->summary }}
@if($submission->details){{ \Illuminate\Support\Str::limit($submission->details, 1200) }}@endif
@if($submission->proposed_source_url)Proposed source: {{ $submission->proposed_source_url }}@endif
From: {{ $submission->submitter_name ?: 'anonymous' }} {{ $submission->submitter_email }}

Review: {{ $adminUrl }}
