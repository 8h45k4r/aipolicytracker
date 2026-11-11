@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-3xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Declared AI system</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $profile->name }}</h1>
    <p class="mt-2 text-sm text-brand-muted">{{ $profile->summary() }} · saved {{ $profile->created_at->format('j M Y') }} · <a href="{{ $profile->url() }}">open the screen</a></p>
    @if(session('status') === 'system-updated')<p class="mt-4 rounded-md border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">System details saved.</p>@endif

    <section class="mt-8" aria-labelledby="screen-h">
        <h2 id="screen-h" class="section-title">What the screen finds</h2>
        <p class="mt-2 text-sm text-brand-body">{{ $summary['total'] }} {{ \Illuminate\Support\Str::plural('duty', $summary['total']) }} screened for this system: {{ $summary['in_force'] }} in force, {{ $summary['undated'] }} with no recorded date.@if($summary['next_date']) The next date is {{ $summary['next_date']->format('j F Y') }}: {{ $summary['next_title'] }}.@endif</p>
        <p class="mt-2 meta">An educational screen over the recorded scope of each law, not a determination that a duty applies. Confirm against the official source.</p>
    </section>

    <section class="mt-8" aria-labelledby="wb-h">
        <h2 id="wb-h" class="section-title">Compliance workbook</h2>
        @if($canWorkbook && $current)
        <p class="mt-2 text-sm text-brand-body">One XLSX for this system: a gap-analysis sheet with every screened duty (status, gap, owner and target date for you to complete), application dates and recorded deadlines, the controls that meet each duty, the evidence a reviewer expects, the official sources, and the history of what changed in the records. A new version is built when a record behind it changes, and you are emailed what changed.</p>
        <p class="mt-4 flex flex-wrap gap-2"><a href="{{ route('profiles.workbook', $profile) }}" class="btn-primary" data-track="workbook_download">Download version {{ $current->version }} (XLSX)</a><a href="{{ app(\App\Services\Applicability\ObligationsRegister::class)->url('csv', $profile->answers) }}" class="btn-secondary" data-track="register_csv">Duties only, CSV</a></p>
        @if($versions->count() > 0)
        <h3 class="mt-6 font-semibold text-brand-navy">Versions</h3>
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm" aria-label="Workbook versions">
            @foreach($versions as $v)
            @php($c = $v->changeCounts())
            <li class="flex flex-wrap items-start justify-between gap-3 py-2">
                <span><span class="font-medium text-brand-navy">Version {{ $v->version }}</span> <span class="meta">· built {{ $v->created_at->format('j M Y') }} ·
                    @if($v->record_changes === null) first version, {{ count($v->snapshot) }} {{ \Illuminate\Support\Str::plural('duty', count($v->snapshot)) }}
                    @else {{ $c['added'] }} added, {{ $c['removed'] }} no longer screened, {{ $c['changed'] }} changed @endif</span>
                    @if($v->record_changes && $v->hasRecordChanges())
                    <ul class="mt-1 ml-4 list-disc text-xs text-brand-body">
                        @foreach(['added' => 'Added', 'removed' => 'No longer screened', 'changed' => 'Changed'] as $key => $word)
                        @foreach(array_slice($v->record_changes[$key] ?? [], 0, 5) as $row)
                        <li>{{ $word }}: {{ $row['title'] }} ({{ $row['instrument'] }})@if(!empty($row['fields'])): {{ implode(', ', \App\Services\Workbooks\ComplianceWorkbook::fieldNames($row['fields'])) }}@endif</li>
                        @endforeach
                        @endforeach
                    </ul>
                    @endif
                </span>
                <a href="{{ route('profiles.workbook.version', [$profile, $v->version]) }}" class="text-sm" data-track="workbook_version_download">XLSX</a>
            </li>
            @endforeach
        </ul>
        @endif
        @else
        <p class="mt-2 text-sm text-brand-body">The duties for this system are free to download from the screen as a register (<a href="{{ app(\App\Services\Applicability\ObligationsRegister::class)->url('xlsx', $profile->answers) }}" data-track="register_xlsx">XLSX</a>, <a href="{{ app(\App\Services\Applicability\ObligationsRegister::class)->url('csv', $profile->answers) }}" data-track="register_csv">CSV</a>). Pro keeps a compliance workbook for each saved system: deadlines, controls, expected evidence and sources in one file, rebuilt when a record behind it changes, with an email saying what changed.</p>
        <p class="mt-3"><a href="{{ route('pricing') }}" class="btn-secondary" data-track="workbook_upsell">See Pro</a></p>
        @endif
    </section>

    <section class="mt-10" aria-labelledby="sys-h">
        <h2 id="sys-h" class="section-title">About this system</h2>
        <p class="mt-1 text-sm text-brand-body">What you tell us here goes into the workbook's System sheet. It is not used for anything else and is not shared.</p>
        <form method="post" action="{{ route('profiles.update', $profile) }}" class="mt-4 space-y-4">
            @csrf @method('PATCH')
            <div><label for="sys-name" class="label">Name</label><input id="sys-name" name="name" class="input" required maxlength="120" value="{{ old('name', $profile->name) }}">@error('name')<p class="mt-1 text-sm text-state-bad">{{ $message }}</p>@enderror</div>
            <div><label for="sys-desc" class="label">What it does</label><textarea id="sys-desc" name="system_description" class="input" rows="3" maxlength="1000">{{ old('system_description', $profile->system_description) }}</textarea>@error('system_description')<p class="mt-1 text-sm text-state-bad">{{ $message }}</p>@enderror</div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="sys-owner" class="label">Owner</label><input id="sys-owner" name="system_owner" class="input" maxlength="120" value="{{ old('system_owner', $profile->system_owner) }}" placeholder="A role or team"></div>
                <div><label for="sys-life" class="label">Stage</label><select id="sys-life" name="lifecycle" class="input"><option value="">Not stated</option>@foreach(\App\Models\ApplicabilityProfile::LIFECYCLE as $key => $label)<option value="{{ $key }}" @selected(old('lifecycle', $profile->lifecycle) === $key)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <button type="submit" class="btn-primary">Save details</button>
        </form>
    </section>
    <x-site.disclaimer class="mt-10" />
    <p class="mt-6 text-sm"><a href="{{ route('following.index') }}">Back to what you follow</a></p>
</div>
@endsection
