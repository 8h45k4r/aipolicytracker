@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-4 flex flex-wrap items-start gap-4">
        <x-site.initials :name="$voice['name']" :voice="$voice" size="h-28 w-28 text-3xl" :eager="true" :alt="'Photo of '.$voice['name']" />
        <div class="min-w-0">
            <p class="eyebrow">{{ \App\Services\Voices\VoiceCatalog::CATEGORIES[$voice['category']] ?? $voice['category'] }}</p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $voice['name'] }}</h1>
            <p class="mt-1 text-lg text-brand-body">{{ $voice['role'] }}</p>
            <p class="mt-1 text-sm text-brand-muted">{{ $voice['organisation'] }} · @if($jurisdiction)<a href="{{ $jurisdiction->url() }}">{{ $jurisdiction->name }}</a>@else International @endif @if($voice['since']) · in the role since <time datetime="{{ $voice['since'] }}">{{ \Illuminate\Support\Carbon::parse($voice['since'])->format('F Y') }}</time>@endif @unless($voice['current']) · <strong>no longer in the role</strong>@endunless</p>
            <p class="mt-2 flex flex-wrap gap-1.5">@foreach($voice['topics'] as $t)<a href="{{ route('voices.index', ['topic' => $t]) }}" class="chip" rel="nofollow">{{ \App\Services\Voices\VoiceCatalog::TOPICS[$t] ?? $t }}</a>@endforeach</p>
        </div>
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="role-h">
                <h2 id="role-h" class="section-title">Role in AI policy</h2>
                <p class="prose-policy mt-2">{{ $voice['summary'] }}</p>
                <p class="mt-2 text-sm"><a href="{{ $voice['official_profile_url'] }}" rel="nofollow noopener" data-track="voice_official_profile">Official profile</a></p>
            </section>

            @if($policies->isNotEmpty())
            <section class="mt-8" aria-labelledby="laws-h">
                <h2 id="laws-h" class="section-title">Related laws and policies on record</h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach($policies as $p)
                    <li><a href="{{ $p->url() }}" class="block h-full rounded-lg border border-brand-line p-3 no-underline hover:border-brand-navy"><span class="block font-medium text-brand-navy">{{ $p->short_title ?: $p->title }}</span><span class="text-xs text-brand-muted">{{ $p->jurisdiction->name }} · {{ $p->statusEnum()->label() }}</span></a></li>
                    @endforeach
                </ul>
            </section>
            @endif

            @if(! empty($voice['statements']))
            <section class="mt-8" aria-labelledby="st-h">
                <h2 id="st-h" class="section-title">Public statements in the role</h2>
                <ol class="mt-3 relative border-l-2 border-brand-line pl-5 space-y-4">
                    @foreach($voice['statements'] as $st)
                    <li class="relative"><span class="absolute -left-[27px] top-1.5 h-3 w-3 rounded-full bg-brand-blue ring-4 ring-white" aria-hidden="true"></span>
                        @if($st['date'] ?? null)<time datetime="{{ $st['date'] }}" class="font-mono text-xs text-brand-muted">{{ \Illuminate\Support\Carbon::parse($st['date'])->format('j M Y') }}</time>@endif
                        <a href="{{ $st['url'] }}" rel="nofollow noopener" class="block font-medium text-brand-navy hover:underline">{{ $st['title'] }}</a>
                        <span class="text-xs text-brand-muted">{{ $st['publisher'] }}</span></li>
                    @endforeach
                </ol>
            </section>
            @endif
        </div>

        <aside class="space-y-6 text-sm">
            <div class="card-flat p-4">
                <p class="label">Review status</p>
                <p class="mt-1">@if($voice['review_status'] === 'verified')Verified by {{ $voice['reviewed_by'] }} on {{ $voice['last_verified_at'] }}.@else<strong>Pending review.</strong> Written from the official sources below; not yet confirmed by a reviewer.@endif</p>
                <p class="mt-1 meta">Sources last checked <time datetime="{{ $checked->toDateString() }}">{{ $checked->format('j F Y') }}</time>.</p>
            </div>
            @if(\App\Services\Voices\VoiceCatalog::photoUrl($voice))
            <p class="meta" data-photo-credit>Photo: {{ $voice['photo']['author'] ?: 'unknown author' }}, <a href="{{ $voice['photo']['license_url'] ?: $voice['photo']['source_url'] }}" rel="nofollow noopener license">{{ $voice['photo']['license'] }}</a>, via <a href="{{ $voice['photo']['source_url'] }}" rel="nofollow noopener">Wikimedia Commons</a>. Cropped.</p>
            @endif
            <div>
                <p class="label">Sources</p>
                <ul class="mt-2 space-y-2">@foreach($voice['sources'] as $s)<li><a href="{{ $s['url'] }}" rel="nofollow noopener">{{ $s['title'] }}</a><span class="block text-xs text-brand-muted">{{ $s['publisher'] }} · accessed {{ $s['accessed'] }}</span></li>@endforeach</ul>
            </div>
            @if($peers->isNotEmpty())
            <div>
                <p class="label">Related voices</p>
                <ul class="mt-2 space-y-2">@foreach($peers as $p)<li class="flex items-center gap-2"><x-site.initials :name="$p['name']" :voice="$p" size="h-9 w-9 text-xs" /><a href="{{ route('voices.show', $p['slug']) }}">{{ $p['name'] }}</a></li>@endforeach</ul>
            </div>
            @endif
            <p class="meta">Something wrong or out of date, or want a profile removed? <a href="{{ route('contribute') }}">Tell us</a>.</p>
        </aside>
    </div>
</div>
@endsection
