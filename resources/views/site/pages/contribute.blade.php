@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Contribute</h1>
    <p class="mt-3 prose-policy">Corrections, official sources and new records are welcome. Everything submitted here is stored as <span class="font-medium">pending review</span> and is never published until a reviewer has checked it against the official source and applied it through the repository.</p>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 text-sm">
        <div class="card-flat p-4"><p class="font-semibold text-slate-900">Report an error</p><p class="mt-1 text-slate-700">Use the "Report a correction" button on any page, or the form below. Say which field is wrong and link the official source that shows the correct value.</p></div>
        <div class="card-flat p-4"><p class="font-semibold text-slate-900">Propose an official source</p><p class="mt-1 text-slate-700">Found a primary source we should link, especially for Nepal, India or the UAE where document URLs are incomplete? Submit it with the record it belongs to.</p></div>
        <div class="card-flat p-4"><p class="font-semibold text-slate-900">Submit a policy record</p><p class="mt-1 text-slate-700">Prefer a pull request: copy an existing YAML file under <code class="rounded bg-slate-100 px-1">data/policies</code>, run <code class="rounded bg-slate-100 px-1">php artisan policy:validate</code>, and use the PR template's source table. Or outline it below and a maintainer will draft it.</p></div>
        <div class="card-flat p-4"><p class="font-semibold text-slate-900">Contributor expectations</p><p class="mt-1 text-slate-700">Official or openly licensed sources only, your own words, no legal conclusions, honest verification status, and no personal data. See CONTRIBUTING.md and SOURCE_ATTRIBUTION.md in the repository.</p></div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2"><a href="{{ config('aipolicytracker.github_url') }}/issues" rel="noopener" class="btn-secondary" data-track="github_click">GitHub issues</a><a href="{{ config('aipolicytracker.github_url') }}/pulls" rel="noopener" class="btn-secondary" data-track="github_click">Pull requests</a></div>

    <form method="post" action="{{ route('contribute.store') }}" class="mt-8 card-flat p-4 sm:p-6 space-y-4" data-track="submission_submit">
        @csrf
        <h2 class="section-title">Submission form</h2>
        @if($errors->any())<div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900" role="alert"><p class="font-medium">Please fix the following:</p><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        <div><label for="s-type" class="label">What are you submitting?</label><select id="s-type" name="type" class="input" required>@foreach($types as $k => $label)<option value="{{ $k }}" @selected(old('type', $prefill['type']) === $k)>{{ $label }}</option>@endforeach</select></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="s-subject-type" class="label">Related record type</label><select id="s-subject-type" name="subject_type" class="input"><option value="">Not specific</option>@foreach(['policy','jurisdiction','obligation','change','other'] as $t)<option value="{{ $t }}" @selected(old('subject_type', $prefill['subject_type']) === $t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
            <div><label for="s-subject-slug" class="label">Record slug (from the URL)</label><input id="s-subject-slug" name="subject_slug" class="input" value="{{ old('subject_slug', $prefill['subject_slug']) }}" placeholder="e.g. eu-ai-act" pattern="[a-z0-9-]*"></div>
        </div>
        <div><label for="s-summary" class="label">Summary <span class="text-rose-700" aria-hidden="true">*</span></label><input id="s-summary" name="summary" class="input" required minlength="10" maxlength="300" value="{{ old('summary') }}" aria-describedby="s-summary-help"><p id="s-summary-help" class="mt-1 text-xs text-slate-500">One sentence: what is wrong, or what should be added.</p>@error('summary')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
        <div><label for="s-details" class="label">Details</label><textarea id="s-details" name="details" rows="5" class="input" maxlength="5000">{{ old('details') }}</textarea></div>
        <div><label for="s-url" class="label">Official source URL</label><input id="s-url" name="proposed_source_url" type="url" class="input" value="{{ old('proposed_source_url') }}" placeholder="https://…">@error('proposed_source_url')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label for="s-name" class="label">Your name</label><input id="s-name" name="submitter_name" class="input" value="{{ old('submitter_name') }}"></div>
            <div><label for="s-email" class="label">Email (for follow-up)</label><input id="s-email" name="submitter_email" type="email" class="input" value="{{ old('submitter_email') }}">@error('submitter_email')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
            <div><label for="s-aff" class="label">Affiliation</label><input id="s-aff" name="submitter_affiliation" class="input" value="{{ old('submitter_affiliation') }}"></div>
        </div>
        <div class="hidden" aria-hidden="true"><label for="s-website">Website</label><input id="s-website" name="website" tabindex="-1" autocomplete="off"></div>
        <input type="hidden" name="source_page" value="{{ url()->previous() }}">
        <p class="text-xs text-slate-500">By submitting you agree that your contribution may be published under {{ config('aipolicytracker.data_license') }} once reviewed. Do not include personal data about third parties.</p>
        <button type="submit" class="btn-primary">Submit for review</button>
    </form>
</div>
@endsection
