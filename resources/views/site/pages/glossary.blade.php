@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI governance glossary</h1>
    <p class="mt-3 prose-policy">{{ $count }} terms used across the records, in plain language. Each names the instrument it comes from: these are explanations for orientation, not the legal text, so follow the source for the binding wording, and the links for the duties that apply.</p>

    <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Glossary sections">
        @foreach($groups as $g)<a href="#{{ $g['id'] }}" class="chip">{{ $g['heading'] }}</a>@endforeach
    </nav>

    <div id="terms">
    @foreach($groups as $g)
        <section class="mt-8" aria-labelledby="{{ $g['id'] }}">
            <h2 id="{{ $g['id'] }}" class="section-title">{{ $g['heading'] }}</h2>
            <dl class="mt-3 divide-y divide-brand-line border-y border-brand-line">
                @foreach($g['terms'] as $t)
                    <div class="py-4 scroll-mt-24" id="{{ $t['id'] }}">
                        <dt class="font-semibold text-brand-navy"><dfn class="not-italic">@if($t['page'] ?? null)<a href="{{ $t['page'] }}" class="text-brand-navy">{{ $t['term'] }}</a>@else{{ $t['term'] }}@endif</dfn> <a href="#{{ $t['id'] }}" class="ml-1 text-xs font-normal text-brand-muted no-underline" aria-label="Link to {{ $t['term'] }}">#</a></dt>
                        <dd class="mt-1 text-sm text-brand-body">
                            <p>{{ $t['definition'] }}</p>
                            @if($t['source'] || $t['see'])
                                <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                    @if($t['source'])<span class="text-brand-muted">Source: <a href="{{ $t['source'][1] }}" rel="noopener" target="_blank">{{ $t['source'][0] }}</a></span>@endif
                                    @foreach($t['see'] as [$label, $url])<a href="{{ $url }}">{{ $label }} →</a>@endforeach
                                </p>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
    </div>

    <p class="mt-8 text-sm text-brand-muted">Missing a term, or think a definition is off? <a href="{{ route('contribute') }}">Tell us</a>; corrections are logged on the <a href="{{ route('corrections') }}">corrections page</a>.</p>
</div>
@endsection
