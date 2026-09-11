@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $subject ? 'Report a correction' : 'Contribute' }}</h1>
    <p class="mt-3 prose-policy">Corrections, official sources and new records are welcome. Everything submitted here is stored as <span class="font-medium">pending review</span> and is never published until a reviewer has checked it against the official source and applied it through the repository.</p>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 text-sm">
        <div class="card-flat p-4"><p class="font-semibold text-brand-navy">Report an error</p><p class="mt-1 text-brand-body">Use the "Report a correction" button on any page, or the form below. Say which field is wrong and link the official source that shows the correct value.</p></div>
        <div class="card-flat p-4"><p class="font-semibold text-brand-navy">Propose an official source</p><p class="mt-1 text-brand-body">Found a primary source we should link, especially for Nepal, India or the UAE where document URLs are incomplete? Submit it with the record it belongs to.</p></div>
        <div class="card-flat p-4"><p class="font-semibold text-brand-navy">Submit a policy record</p><p class="mt-1 text-brand-body">Prefer a pull request: copy an existing YAML file under <code class="rounded bg-brand-paper px-1">data/policies</code>, run <code class="rounded bg-brand-paper px-1">php artisan policy:validate</code>, and use the PR template's source table. Or outline it below and a maintainer will draft it.</p></div>
        <div class="card-flat p-4"><p class="font-semibold text-brand-navy">Contributor expectations</p><p class="mt-1 text-brand-body">Official or openly licensed sources only, your own words, no legal conclusions, honest verification status, and no personal data. See CONTRIBUTING.md and SOURCE_ATTRIBUTION.md in the repository.</p></div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2"><a href="{{ config('aipolicytracker.github_url') }}/issues" rel="noopener" class="btn-secondary" data-track="github_click">GitHub issues</a><a href="{{ config('aipolicytracker.github_url') }}/pulls" rel="noopener" class="btn-secondary" data-track="github_click">Pull requests</a></div>

    <form method="post" action="{{ route('contribute.store') }}" class="mt-8 card-flat p-4 sm:p-6 space-y-4" data-track="submission_submit">
        @csrf
        <h2 class="section-title">Submission form</h2>
        @if($errors->any())<div class="rounded-sm border border-state-bad/30 bg-state-badbg p-3 text-sm text-state-bad" role="alert"><p class="font-medium">Please fix the following:</p><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        <div><label for="s-type" class="label">What are you submitting?</label><select id="s-type" name="type" class="input" required>@foreach($types as $k => $label)<option value="{{ $k }}" @selected(old('type', $prefill['type']) === $k)>{{ $label }}</option>@endforeach</select></div>
        @if($subject)
        <div class="rounded-sm border border-brand-navy/20 bg-brand-paper p-4 text-sm" data-correction-context>
            <p class="eyebrow">Record you are correcting</p>
            <p class="mt-1 font-semibold text-brand-navy"><a href="{{ $subject['url'] }}">{{ $subject['title'] }}</a>@if($subject['jurisdiction']) <span class="font-normal text-brand-muted">· {{ $subject['jurisdiction'] }}</span>@endif</p>
            <dl class="mt-2 grid gap-x-6 gap-y-1 sm:grid-cols-2 text-xs text-brand-muted">
                <div><dt class="inline">Type:</dt> <dd class="inline">{{ ucfirst($subject['type']) }}</dd></div>
                <div><dt class="inline">Slug:</dt> <dd class="inline font-mono">{{ $subject['slug'] }}</dd></div>
                @if($subject['official_source_url'])<div class="sm:col-span-2"><dt class="inline">Official source on file:</dt> <dd class="inline"><a href="{{ $subject['official_source_url'] }}" rel="noopener" class="break-all">{{ $subject['source_title'] ?: \Illuminate\Support\Str::limit($subject['official_source_url'], 80) }}</a></dd></div>@endif
                @if($subject['content_version'])<div><dt class="inline">Content version:</dt> <dd class="inline">{{ $subject['content_version'] }}</dd></div>@endif
            </dl>
            <input type="hidden" name="subject_type" value="{{ $subject['type'] }}">
            <input type="hidden" name="subject_slug" value="{{ $subject['slug'] }}">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="s-field" class="label">Which field is wrong?</label><select id="s-field" name="field" class="input" data-field-select><option value="">Not a specific field</option>@foreach($subject['fields'] as $key => $f)<option value="{{ $key }}" data-current="{{ $f['value'] ?? '' }}" @selected(old('field', $prefill['field']) === $key)>{{ $f['label'] }}</option>@endforeach</select></div>
            <div><label for="s-current" class="label">Value currently shown</label><textarea id="s-current" name="current_value" rows="2" class="input bg-brand-paper" readonly data-current-value maxlength="4000">{{ old('current_value', $prefill['field'] ? ($subject['fields'][$prefill['field']]['value'] ?? '—') : '') }}</textarea><p class="mt-1 text-xs text-brand-muted">Filled in automatically from the record; empty fields show —.</p></div>
        </div>
        <div><label for="s-proposed" class="label">Correct value (as stated in the official source)</label><textarea id="s-proposed" name="proposed_value" rows="2" class="input" maxlength="4000">{{ old('proposed_value') }}</textarea>@error('proposed_value')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        @else
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="s-subject-type" class="label">Related record type</label><select id="s-subject-type" name="subject_type" class="input"><option value="">Not specific</option>@foreach(['policy','jurisdiction','obligation','change','other'] as $t)<option value="{{ $t }}" @selected(old('subject_type', $prefill['subject_type']) === $t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
            <div><label for="s-subject-slug" class="label">Record slug (from the URL)</label><input id="s-subject-slug" name="subject_slug" class="input" value="{{ old('subject_slug', $prefill['subject_slug']) }}" placeholder="e.g. eu-ai-act" pattern="[a-z0-9-]*"><p class="mt-1 text-xs text-brand-muted">Tip: use the "Report a correction" button on a record page and the form fills this in for you.</p></div>
        </div>
        @endif
        <div><label for="s-summary" class="label">Summary <span class="text-state-bad" aria-hidden="true">*</span></label><input id="s-summary" name="summary" class="input" required minlength="10" maxlength="300" value="{{ old('summary', $subject && $prefill['field'] ? ($subject['fields'][$prefill['field']]['label'] ?? '').' for '.$subject['title'].' is wrong' : '') }}" aria-describedby="s-summary-help" data-summary-input><p id="s-summary-help" class="mt-1 text-xs text-brand-muted">One sentence: what is wrong, or what should be added.</p>@error('summary')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        <div><label for="s-details" class="label">Details</label><textarea id="s-details" name="details" rows="5" class="input" maxlength="5000">{{ old('details') }}</textarea></div>
        <div><label for="s-url" class="label">Official source URL</label><input id="s-url" name="proposed_source_url" type="url" class="input" value="{{ old('proposed_source_url', $subject['official_source_url'] ?? '') }}" placeholder="https://…"><p class="mt-1 text-xs text-brand-muted">Prefilled with the source on file; replace it if a different document shows the correct value.</p>@error('proposed_source_url')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label for="s-name" class="label">Your name</label><input id="s-name" name="submitter_name" class="input" value="{{ old('submitter_name') }}"></div>
            <div><label for="s-email" class="label">Email (for follow-up)</label><input id="s-email" name="submitter_email" type="email" class="input" value="{{ old('submitter_email') }}">@error('submitter_email')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
            <div><label for="s-aff" class="label">Affiliation</label><input id="s-aff" name="submitter_affiliation" class="input" value="{{ old('submitter_affiliation') }}"></div>
        </div>
        <div class="hidden" aria-hidden="true"><label for="s-website">Website</label><input id="s-website" name="website" tabindex="-1" autocomplete="off"></div>
        <input type="hidden" name="source_page" value="{{ $subject['url'] ?? url()->previous() }}">
        <p class="text-xs text-brand-muted">By submitting you agree that your contribution may be published under {{ config('aipolicytracker.data_license') }} once reviewed. Do not include personal data about third parties.</p>
        <button type="submit" class="btn-primary">Submit for review</button>
    </form>
</div>
@endsection
