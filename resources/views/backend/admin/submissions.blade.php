@extends('backend.layouts.app', ['title' => 'Submissions and feedback'])
@section('content')
<x-backend.page-header title="Submissions and feedback" description="Everything sent through the public contribution form: corrections, proposed sources, new policy records and reviewer applications. Tick several and record one decision for all of them." />

<div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-backend.stat label="Waiting for review" :value="number_format($byStatus['pending_review'] ?? 0)" :href="route('backend.admin.submissions', ['status' => 'pending_review'])" :tone="($byStatus['pending_review'] ?? 0) ? 'text-state-warn' : null" />
    <x-backend.stat label="Received, last 30 days" :value="number_format(array_sum($trend))" :trend="$trend" :href="route('backend.admin.submissions', ['from' => now()->subDays(29)->toDateString()])" />
    <x-backend.stat label="Approved" :value="number_format($byStatus['approved'] ?? 0)" :href="route('backend.admin.submissions', ['status' => 'approved'])" />
    <x-backend.stat label="All time" :value="number_format($byStatus->sum())" :href="route('backend.admin.submissions')" />
</div>

{{-- Status as tabs, each with its count; type, search and dates in the bar beneath. --}}
<nav class="adm-tabs" aria-label="Status">
    <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" @if(! $status) aria-current="page" @endif>Any status<span class="adm-count">{{ $byStatus->sum() }}</span></a>
    @foreach(\App\Enums\SubmissionStatus::cases() as $s)
    <a href="{{ request()->fullUrlWithQuery(['status' => $s->value, 'page' => null]) }}" @if($status === $s->value) aria-current="page" @endif>{{ $s->label() }}<span class="adm-count">{{ $byStatus[$s->value] ?? 0 }}</span></a>
    @endforeach
</nav>

<x-backend.filters :action="route('backend.admin.submissions')" :filters="$filters" :export="route('backend.admin.submissions.export')" placeholder="Summary, details, submitter or record" :total="$submissions->total()" noun="submission" :keep-sort="false">
    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
    <div><label for="f-type" class="adm-label">Type</label><select id="f-type" name="type" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">All types</option>@foreach(\App\Models\ContributorSubmission::TYPES as $k => $label)<option value="{{ $k }}" @selected($type === $k)>{{ $label }} ({{ $byType[$k] ?? 0 }})</option>@endforeach</select></div>
    <div><label for="f-sort" class="adm-label">Sort</label><select id="f-sort" name="sort" class="input !min-h-[38px] !py-1.5 !w-auto">@foreach(['received' => 'Received', 'type' => 'Type', 'status' => 'Status', 'summary' => 'Summary'] as $k => $label)<option value="{{ $k }}" @selected($filters->sort === $k)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="f-dir" class="adm-label">Order</label><select id="f-dir" name="dir" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="desc" @selected($filters->dir === 'desc')>Newest / Z–A</option><option value="asc" @selected($filters->dir === 'asc')>Oldest / A–Z</option></select></div>
</x-backend.filters>

@if($submissions->isEmpty())<div class="mt-6"><x-site.empty title="No submissions match" :reset="route('backend.admin.submissions')">Change the type, status, search or dates, or clear them to see every submission.</x-site.empty></div>@else
<x-backend.decision-bar id="bulk-submissions" />
<div class="mt-3 divide-y divide-brand-line rounded-md border border-brand-line bg-white">
    @foreach($submissions as $s)<x-backend.submission :submission="$s" bulk="bulk-submissions" />@endforeach
</div>
<nav class="mt-4" aria-label="Pagination">{{ $submissions->links() }}</nav>
@endif
@endsection
