@extends('site.layouts.app')
@section('content')
<x-site.listing-shell :seo="$seo" :filters="$filters" :options="$options" :paginator="$obligations" mode="obligations"
    :heading="!empty($filters['category']) && ($c = $options['categories']->firstWhere('slug', $filters['category'])) ? $c->name.': AI obligations' : 'AI compliance obligations'"
    intro="Practical requirements extracted from policy instruments, with the source article, the actors they bind, evidence examples and original framework mappings. Legal requirements are marked; everything else is voluntary guidance.">
    @forelse($obligations as $o)
    <article class="py-4">
        <div class="flex flex-wrap items-center gap-2 text-xs text-brand-muted">
            <span class="badge {{ $o->is_binding ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $o->is_binding ? 'Legal requirement' : 'Voluntary guidance' }}</span>
            <span>{{ str_replace('_', ' ', $o->category) }}</span>
            <span aria-hidden="true">·</span>
            <a href="{{ $o->policyInstrument->jurisdiction->url() }}" class="font-medium text-brand-body">{{ $o->policyInstrument->jurisdiction->name }}</a>
        </div>
        <h3 class="mt-1.5 text-base font-semibold text-brand-navy"><a href="{{ $o->url() }}" class="hover:underline">{{ $o->title }}</a></h3>
        <p class="text-xs text-brand-muted"><a href="{{ $o->policyInstrument->url() }}" class="hover:text-brand-navy">{{ $o->policyInstrument->short_title ?: $o->policyInstrument->title }}</a>@if($o->source_reference) · {{ $o->source_reference }}@endif</p>
        <p class="mt-2 text-sm text-brand-body line-clamp-3">{{ $o->summary }}</p>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs"><x-site.verified :record="$o" />@if($o->applies_from)<span class="text-brand-muted">Applies from {{ $o->applies_from->format('j M Y') }}</span>@endif</div>
    </article>
    @empty
    <div class="py-6"><x-site.empty :reset="route('obligations.index')" /></div>
    @endforelse
</x-site.listing-shell>
@endsection
