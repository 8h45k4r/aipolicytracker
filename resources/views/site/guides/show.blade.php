@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $page['h1'] }}</h1>
    <p class="mt-3 max-w-3xl prose-policy text-base">{{ $page['summary'] }}</p>
    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <ol class="space-y-6">@foreach($page['steps'] as $s)<li class="relative pl-10"><span class="absolute left-0 top-0 inline-flex h-7 w-7 items-center justify-center rounded-sm bg-brand-navy text-xs font-semibold text-white" aria-hidden="true">{{ $loop->iteration }}</span><h2 class="section-title">{{ $s['title'] }}</h2><p class="prose-policy mt-1">{{ $s['body'] }}</p></li>@endforeach</ol>
            @if($obligations->isNotEmpty())
            <section class="mt-10" aria-labelledby="obl-heading"><h2 id="obl-heading" class="section-title">Obligations referenced in this guide</h2><ul class="mt-2 space-y-2 text-sm">@foreach($obligations as $o)<li><span class="badge {{ $o->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $o->is_binding ? 'Legal' : 'Voluntary' }}</span> <a href="{{ $o->url() }}" class="text-brand-navy hover:underline">{{ $o->title }}</a> <span class="text-xs text-brand-muted">({{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}, {{ $o->policyInstrument->jurisdiction->name }})</span></li>@endforeach</ul></section>
            @endif
            @if($frameworkObligations->isNotEmpty())
            <section class="mt-10" aria-labelledby="xwalk-heading"><h2 id="xwalk-heading" class="section-title">Crosswalk from recorded obligations</h2><p class="mt-1 text-xs text-brand-muted">Original editorial mappings with confidence levels; clause references only, no standard text.</p>
                <div class="table-wrap table-sticky mt-3"><table><caption class="sr-only">Framework crosswalk</caption><thead><tr><th scope="col">Obligation</th><th scope="col">Instrument</th><th scope="col">Framework reference</th><th scope="col">Confidence</th></tr></thead><tbody>
                @foreach($frameworkObligations as $o)@foreach($o->frameworkMappings->where('framework', $page['framework']) as $m)<tr><th scope="row" class="font-medium"><a href="{{ $o->url() }}" class="text-brand-navy hover:underline">{{ $o->title }}</a></th><td>{{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}</td><td>{{ $m->reference }}@if($m->note)<div class="text-xs text-brand-muted">{{ $m->note }}</div>@endif</td><td>{{ $m->confidence_level }}</td></tr>@endforeach @endforeach
                </tbody></table></div></section>
            @endif
            <x-site.faq :items="$page['faq'] ?? []" />
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6"><div class="lg:sticky lg:top-4 space-y-6">
            @if($policies->isNotEmpty())<div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">Policies in this guide</p><ul class="mt-2 space-y-1.5">@foreach($policies as $p)<li><a href="{{ $p->url() }}" class="text-brand-navy hover:underline">{{ $p->short_title ?: $p->title }}</a> <span class="text-xs text-brand-muted">({{ $p->jurisdiction->short_name ?: $p->jurisdiction->name }})</span></li>@endforeach</ul></div>@endif
            <div class="text-sm"><p class="font-semibold text-brand-navy">More guides</p><ul class="mt-2 space-y-1.5">@foreach(config('content.guides') as $s => $g)@if($s !== $slug)<li><a href="{{ route('guides.show', $s) }}" class="text-brand-navy hover:underline">{{ $g['h1'] }}</a></li>@endif @endforeach</ul></div>
            <x-site.certifyi-cta />
        </div></aside>
    </div>
</div>
@endsection
