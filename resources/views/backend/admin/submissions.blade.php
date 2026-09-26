@extends('backend.layouts.app', ['title' => 'Submissions and feedback'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Submissions and feedback</h1>
<p class="mt-1 meta">Everything sent through the public contribution form: corrections, proposed sources, new policy records and reviewer applications. Tick several and record one decision for all of them.</p>
<div class="mt-4 flex flex-wrap gap-2 text-sm">
    <a class="chip {{ !$type ? 'chip-active' : '' }}" href="{{ route('backend.admin.submissions', ['status' => $status]) }}">All types</a>
    @foreach(\App\Models\ContributorSubmission::TYPES as $k => $label)<a class="chip {{ $type === $k ? 'chip-active' : '' }}" href="{{ route('backend.admin.submissions', ['type' => $k, 'status' => $status]) }}">{{ $label }} ({{ $byType[$k] ?? 0 }})</a>@endforeach
</div>
<div class="mt-2 flex flex-wrap gap-2 text-sm">
    <a class="chip {{ !$status ? 'chip-active' : '' }}" href="{{ route('backend.admin.submissions', ['type' => $type]) }}">Any status</a>
    @foreach(\App\Enums\SubmissionStatus::cases() as $s)<a class="chip {{ $status === $s->value ? 'chip-active' : '' }}" href="{{ route('backend.admin.submissions', ['type' => $type, 'status' => $s->value]) }}">{{ $s->label() }} ({{ $byStatus[$s->value] ?? 0 }})</a>@endforeach
</div>
@if($submissions->isEmpty())<div class="mt-6"><x-site.empty title="No submissions match" :reset="route('backend.admin.submissions')">Change the type or status, or clear them to see every submission.</x-site.empty></div>@else
<x-backend.decision-bar id="bulk-submissions" />
<div class="mt-3 divide-y divide-brand-line border-y border-brand-line bg-white">
    @foreach($submissions as $s)<x-backend.submission :submission="$s" bulk="bulk-submissions" />@endforeach
</div>
<nav class="mt-4" aria-label="Pagination">{{ $submissions->links() }}</nav>
@endif
@endsection
