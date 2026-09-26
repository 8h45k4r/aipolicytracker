@extends('site.layouts.app')
@section('content')
@php($catalog = \App\Services\Templates\TemplateCatalog::class)
@php($preview = $version->preview ?? ['sheets' => [], 'outline' => []])
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <div class="flex flex-wrap gap-1.5"><span class="badge bg-brand-navy text-white ring-brand-navy">{{ $catalog::typeLabel($meta['type']) }}</span><span class="badge bg-state-goodbg text-state-good ring-state-good/30">Free · no account</span>@foreach(($meta['frameworks'] ?? []) as $f)<span class="badge-neutral">{{ $catalog::frameworkLabel($f) }}</span>@endforeach</div>
        <h1 class="mt-3 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $meta['title'] }}</h1>
        <p class="mt-3 max-w-[64ch] text-brand-body leading-7">{{ $meta['short'] }}</p>
        <div id="download" class="mt-4 flex flex-wrap gap-2">
            @foreach($version->files as $f)
            <a href="{{ $version->downloadUrl($f['format']) }}" class="btn-primary" data-track="template_download" data-track-label="{{ $meta['slug'] }}:{{ $f['format'] }}" download>Download {{ strtoupper($f['format']) }} <span class="font-normal opacity-80">({{ number_format($f['bytes'] / 1024) }} KB)</span></a>
            @endforeach
            <a href="#preview" class="btn-secondary">Preview</a>
        </div>
        <p class="mt-2 meta">Formats: {{ $formats }} · Version {{ $version->label() }} · Built <time datetime="{{ $version->generated_at->toAtomString() }}">{{ $version->generated_at->format('j M Y') }}</time> from dataset <code>{{ $version->dataset_version }}</code> · {{ config('templates.licence') }}</p>
    </header>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="inside-heading">
                <h2 id="inside-heading" class="section-title">What's inside</h2>
                <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm text-brand-body">@foreach($meta['inside'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                @if($caveat)<p class="mt-3 rounded-sm border border-state-warn/40 bg-state-warnbg p-3 text-sm text-brand-body"><span class="font-medium text-brand-navy">Dates:</span> {{ $caveat }}</p>@endif
            </section>

            <section id="preview" class="mt-8" aria-labelledby="preview-heading">
                <h2 id="preview-heading" class="section-title">Preview</h2>
                <p class="mt-1 text-xs text-brand-muted">The sheets and sections of version {{ $version->label() }}, as built. Columns marked ▾ have a dropdown; ƒ is a formula.</p>
                @foreach($preview['sheets'] as $sheet)
                <details class="mt-3 card-flat" @if($loop->first) open @endif>
                    <summary class="cursor-pointer p-3 text-sm font-medium text-brand-navy">Sheet: {{ $sheet['name'] }} <span class="meta font-normal">· {{ count($sheet['columns']) }} columns · {{ $sheet['row_count'] > 0 ? number_format($sheet['row_count']).' rows from the records' : 'blank, '.number_format($sheet['editable_rows']).' rows ready to fill' }}</span></summary>
                    <div class="table-wrap px-3 pb-3"><table class="text-xs"><caption class="sr-only">First rows of the {{ $sheet['name'] }} sheet</caption>
                        <thead><tr>@foreach($sheet['columns'] as $c)<th scope="col" class="whitespace-nowrap">{{ $c['label'] }}@if($c['type'] === 'select') <span title="Dropdown, {{ $c['options'] }} options">▾</span>@elseif($c['type'] === 'formula') <span title="Formula">ƒ</span>@endif</th>@endforeach</tr></thead>
                        <tbody>@forelse($sheet['rows'] as $row)<tr>@foreach($row as $cell)<td class="max-w-[28ch] truncate">{{ $cell }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($sheet['columns']) }}" class="text-brand-muted">Rows are yours to fill; the dropdowns, formulas and colour rules are already in place.</td></tr>@endforelse</tbody></table></div>
                    @if($sheet['note'])<p class="px-3 pb-3 text-xs text-brand-muted">{{ $sheet['note'] }}</p>@endif
                </details>
                @endforeach
                @if(!empty($preview['outline']))
                <div class="mt-3 card-flat p-3">
                    <p class="text-sm font-medium text-brand-navy">Document outline (DOCX)</p>
                    <ol class="mt-2 space-y-0.5 text-sm text-brand-body">@foreach($preview['outline'] as $h)<li class="{{ $h['level'] === 1 ? 'font-medium' : ($h['level'] === 2 ? 'pl-4' : 'pl-8 text-brand-muted') }}">{{ $h['text'] }}</li>@endforeach</ol>
                </div>
                @endif
            </section>

            @if($covered->isNotEmpty())
            <section class="mt-8" aria-labelledby="covers-heading">
                <h2 id="covers-heading" class="section-title">Duties this template covers <span class="text-sm font-normal text-brand-muted">({{ $covered->count() }})</span></h2>
                <p class="mt-1 text-xs text-brand-muted">Each is cited in the file with its source reference and a link back to the record.</p>
                <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
                    @foreach($covered->take(12) as $o)
                    <li class="py-2"><a href="{{ $o->url() }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $o->title }}</a> <span class="meta">· {{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }} · {{ $o->policyInstrument->jurisdiction->name }}@if($o->source_reference) · {{ $o->source_reference }}@endif</span></li>
                    @endforeach
                </ul>
                @if($covered->count() > 12)<p class="mt-2 text-sm"><a href="{{ route('obligations.index', array_filter(['category' => $meta['covers']['categories'][0] ?? null, 'policy' => $meta['covers']['policies'][0] ?? null])) }}" class="text-brand-blue hover:underline">See all {{ $covered->count() }} duties →</a></p>@endif
            </section>
            @endif

            @if($basis->isNotEmpty())
            <section class="mt-8" aria-labelledby="basis-heading">
                <h2 id="basis-heading" class="section-title">Legal basis</h2>
                <ul class="mt-2 space-y-1.5 text-sm">@foreach($basis as $b)<li><a href="{{ $b['url'] }}" class="text-brand-navy">{{ $b['title'] }}</a> <span class="meta">{{ $b['meta'] }}</span></li>@endforeach</ul>
            </section>
            @endif

            <section class="mt-8" aria-labelledby="versions-heading">
                <h2 id="versions-heading" class="section-title">Version history</h2>
                <div class="table-wrap mt-3"><table><caption class="sr-only">Versions of {{ $meta['title'] }}</caption><thead><tr><th scope="col">Version</th><th scope="col">Built</th><th scope="col">Dataset</th><th scope="col">What changed</th></tr></thead><tbody>
                    @foreach($versions as $v)<tr><td class="whitespace-nowrap font-medium text-brand-navy">{{ $v->label() }}</td><td class="whitespace-nowrap"><time datetime="{{ $v->generated_at->toDateString() }}">{{ $v->generated_at->format('j M Y') }}</time></td><td><code class="text-xs">{{ $v->dataset_version }}</code></td><td>{{ $v->changelog }}</td></tr>@endforeach
                </tbody></table></div>
                <p class="mt-2 text-xs text-brand-muted">Only the latest version is served. A rebuild that changes the content adds a version; a rebuild that does not is skipped.</p>
            </section>

            <x-site.faq :items="$seo->faqItems()" />
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                <div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">At a glance</p>
                    <dl class="mt-2 space-y-1.5 text-brand-body">
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Type</dt><dd>{{ $catalog::typeLabel($meta['type']) }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Formats</dt><dd class="text-right">{{ $formats }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Version</dt><dd>{{ $version->label() }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Built</dt><dd><time datetime="{{ $version->generated_at->toDateString() }}">{{ $version->generated_at->format('j M Y') }}</time></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Citations</dt><dd>{{ number_format($version->stats['citations'] ?? 0) }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Downloads</dt><dd>{{ number_format($versions->sum('downloads')) }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Licence</dt><dd>CC BY 4.0</dd></div>
                    </dl>
                    <div class="mt-3 flex flex-col gap-2">@foreach($version->files as $f)<a href="{{ $version->downloadUrl($f['format']) }}" class="btn-primary" data-track="template_download" data-track-label="{{ $meta['slug'] }}:{{ $f['format'] }}" download>Download {{ strtoupper($f['format']) }}</a>@endforeach</div>
                </div>
                @if($related->isNotEmpty())
                <section aria-labelledby="related-heading"><h2 id="related-heading" class="section-title">Related templates</h2>
                    <ul class="mt-2 space-y-1.5 text-sm">@foreach($related as $r)<li><a href="{{ route('templates.show', $r['slug']) }}" class="text-brand-navy hover:underline">{{ $r['title'] }}</a> <span class="meta">{{ $catalog::typeLabel($r['type']) }}</span></li>@endforeach</ul>
                    <p class="mt-2 text-sm"><a href="{{ route('templates.index') }}" class="text-brand-blue hover:underline">All templates →</a></p>
                </section>
                @endif
                <x-site.subscribe-form id="subscribe" source="templates" topic="templates" topic-label="the templates library" />
            </div>
        </aside>
    </div>
</div>
@endsection
