@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="mt-2 eyebrow">AI governance glossary</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy"><dfn class="not-italic">{{ $t['term'] }}</dfn></h1>
    <x-site.answer-box :text="$t['definition']" label="Definition" class="mt-4" />
    <p class="mt-3 text-sm text-brand-muted">
        @if($t['source'])Source: <a href="{{ $t['source'][1] }}" rel="noopener" target="_blank">{{ $t['source'][0] }}</a>. @endif
        A plain-language explanation for orientation, not the legal text; follow the source for the binding wording.
    </p>
    @if($t['see'])
    <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">@foreach($t['see'] as [$label, $href])<a href="{{ $href }}">{{ $label }} →</a>@endforeach</p>
    @endif

    @if($policies->isNotEmpty())
    <section class="mt-10" aria-labelledby="laws-heading">
        <h2 id="laws-heading" class="section-title">Laws and policies on record that use it</h2>
        <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
            @foreach($policies as $p)
            <li class="py-2.5"><a href="{{ $p->url() }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $p->short_title ?: $p->title }}</a><span class="block text-xs text-brand-muted">{{ $p->jurisdiction?->name }} · {{ $p->statusEnum()->label() }}</span></li>
            @endforeach
        </ul>
    </section>
    @endif

    @if($obligations->isNotEmpty())
    <section class="mt-10" aria-labelledby="duties-heading">
        <h2 id="duties-heading" class="section-title">Duties that mention it</h2>
        <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
            @foreach($obligations as $o)
            <li class="py-2.5"><a href="{{ $o->url() }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $o->title }}</a><span class="block text-xs text-brand-muted">{{ $o->policyInstrument?->short_title ?: $o->policyInstrument?->title }}@if($o->policyInstrument?->jurisdiction) · {{ $o->policyInstrument->jurisdiction->name }}@endif</span></li>
            @endforeach
        </ul>
    </section>
    @endif

    @if($related)
    <section class="mt-10" aria-labelledby="related-heading">
        <h2 id="related-heading" class="section-title">Related terms</h2>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">
            @foreach($related as $r)
            <li class="rounded-sm border border-brand-line p-3"><a href="{{ \App\Services\Glossary\GlossaryTerms::url($r['id']) }}" class="font-medium">{{ $r['term'] }}</a><span class="mt-1 block text-xs text-brand-muted">{{ \Illuminate\Support\Str::limit($r['definition'], 110) }}</span></li>
            @endforeach
        </ul>
    </section>
    @endif

    <p class="mt-10 text-sm text-brand-muted"><a href="{{ route('glossary') }}">All glossary terms</a> · Think a definition is off? <a href="{{ route('contribute') }}">Tell us</a>; corrections are logged on the <a href="{{ route('corrections') }}">corrections page</a>.</p>
</div>
@endsection
