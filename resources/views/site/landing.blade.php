@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">{{ $page['h1'] }}</h1>
    <p class="mt-3 max-w-3xl prose-policy text-base">{{ $page['answer'] }}</p>
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">@foreach($jurisdictions as $j)<x-site.verified :record="$j" class="!text-sm" />@endforeach</div>

    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            @if($primary)
            <section aria-labelledby="primary-heading" class="card-flat p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-2 text-sm"><x-site.status-badge :status="$primary->statusEnum()" /><span class="text-brand-muted">{{ $primary->typeEnum()->label() }}</span>@if($primary->is_binding)<span class="badge bg-brand-navy text-white ring-brand-navy">Binding</span>@endif</div>
                <h2 id="primary-heading" class="mt-2 text-lg font-semibold text-brand-navy"><a href="{{ $primary->url() }}" class="hover:underline">{{ $primary->title }}</a></h2>
                <p class="mt-2 text-sm text-brand-body">{{ $primary->summary_plain }}</p>
                @if($primary->deadlines->isNotEmpty())
                <div class="table-wrap mt-3"><table><caption class="sr-only">Key dates</caption><thead><tr><th scope="col">Date</th><th scope="col">Milestone</th><th scope="col">Status</th></tr></thead><tbody>@foreach($primary->deadlines->take(8) as $d)<tr><td class="whitespace-nowrap font-mono">{{ $d->displayDate() }}</td><td>{{ $d->title }}</td><td class="whitespace-nowrap">{{ ucfirst($d->deadline_status) }}</td></tr>@endforeach</tbody></table></div>
                @endif
                <p class="mt-3 text-sm"><a href="{{ $primary->url() }}" class="text-brand-blue font-medium hover:underline">Full record: obligations, sources and change history</a></p>
            </section>
            @endif

            @foreach($page['sections'] as $s)
            <section class="mt-8" aria-labelledby="s-{{ $loop->index }}"><h2 id="s-{{ $loop->index }}" class="section-title">{{ $s['heading'] }}</h2><p class="prose-policy mt-2">{{ $s['body'] }}</p></section>
            @endforeach

            <section class="mt-8" aria-labelledby="policies-heading"><h2 id="policies-heading" class="section-title">Recorded policy instruments</h2><div class="mt-1 divide-y divide-brand-line border-y border-brand-line">@foreach($policies as $p)<x-site.policy-row :policy="$p" />@endforeach</div></section>

            @if($obligations->isNotEmpty())
            <section class="mt-8" aria-labelledby="obl-heading"><h2 id="obl-heading" class="section-title">Key obligations</h2><ul class="mt-2 space-y-2 text-sm">@foreach($obligations as $o)<li><span class="badge {{ $o->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $o->is_binding ? 'Legal' : 'Voluntary' }}</span> <a href="{{ $o->url() }}" class="text-brand-navy hover:underline">{{ $o->title }}</a> <span class="text-xs text-brand-muted">({{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }})</span></li>@endforeach</ul></section>
            @endif

            <section class="mt-8" aria-labelledby="changes-heading"><h2 id="changes-heading" class="section-title">Latest changes</h2><div class="mt-1 divide-y divide-brand-line border-y border-brand-line">@forelse($changes as $c)<x-site.change-item :change="$c" compact />@empty<p class="py-4 text-sm text-brand-muted">No change events recorded yet.</p>@endforelse</div></section>
            <x-site.faq :items="$page['faq'] ?? []" />
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6"><div class="lg:sticky lg:top-4 space-y-6">
            <div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">Jurisdiction pages</p><ul class="mt-2 space-y-1.5">@foreach($jurisdictions as $j)<li><a href="{{ $j->url() }}" class="text-brand-navy hover:underline">AI regulation in {{ $j->nameWithArticle() }}</a></li>@endforeach</ul></div>
            @if($deadlines->isNotEmpty())<div class="text-sm"><p class="font-semibold text-brand-navy">Upcoming dates</p><ul class="mt-2 space-y-2">@foreach($deadlines as $d)<li><time class="font-mono text-xs text-brand-muted" datetime="{{ $d->due_on->toDateString() }}">{{ $d->displayDate() }}</time><div><a href="{{ $d->policyInstrument->url() }}" class="text-brand-navy hover:underline">{{ $d->title }}</a></div></li>@endforeach</ul></div>@endif
            <div class="text-sm"><p class="font-semibold text-brand-navy">Tools</p><ul class="mt-2 space-y-1.5"><li><a href="{{ route('tools.applicability', ['jurisdictions' => $jurisdictions->pluck('slug')->all()]) }}" class="text-brand-navy hover:underline" rel="nofollow">Applicability check</a></li><li><a href="{{ route('compare.index') }}" class="text-brand-navy hover:underline">Compare jurisdictions</a></li><li><a href="{{ route('guides.index') }}" class="text-brand-navy hover:underline">Guides</a></li></ul></div>
            <x-site.certifyi-cta />
        </div></aside>
    </div>
</div>
@endsection
