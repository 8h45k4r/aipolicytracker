@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">{{ ucfirst($reviewer['role'] ?? 'reviewer') }}</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $reviewer['name'] }}</h1>
    <p class="mt-2 meta">@if(!empty($reviewer['joined_on']))On the roster since {{ \Illuminate\Support\Carbon::parse($reviewer['joined_on'])->format('F Y') }} · @endif{{ number_format($reviewer['verified'] ?? 0) }} {{ \Illuminate\Support\Str::plural('record', $reviewer['verified'] ?? 0) }} verified</p>
    @if(!empty($reviewer['bio']))<p class="mt-4 text-brand-body leading-7">{{ $reviewer['bio'] }}</p>@endif
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <section class="card-flat p-4 text-sm" aria-labelledby="scope-h"><h2 id="scope-h" class="section-title !text-lg">Scope</h2>
            @if(!empty($reviewer['expertise']))<p class="mt-2"><span class="text-brand-muted">Expertise:</span> {{ implode(', ', $reviewer['expertise']) }}</p>@endif
            @if(!empty($reviewer['jurisdiction_names']))<p class="mt-1"><span class="text-brand-muted">Covers:</span> {{ implode(', ', $reviewer['jurisdiction_names']) }}</p>@endif
            @if(!empty($reviewer['affiliations']))<p class="mt-1"><span class="text-brand-muted">Affiliations:</span> @foreach($reviewer['affiliations'] as $a){{ $a['organisation'] }}@if(!empty($a['role'])) ({{ $a['role'] }})@endif @if(isset($a['current']) && !$a['current']), former @endif{{ !$loop->last ? '; ' : '' }}@endforeach</p>@endif
            @if(!empty($reviewer['links']))<p class="mt-1"><span class="text-brand-muted">Links:</span> @foreach($reviewer['links'] as $l)<a href="{{ $l['url'] }}" rel="me noopener">{{ $l['label'] }}</a>{{ !$loop->last ? ' · ' : '' }}@endforeach</p>@endif
        </section>
        <section class="card-flat p-4 text-sm" aria-labelledby="int-h"><h2 id="int-h" class="section-title !text-lg">Declared interests</h2>
            @foreach($reviewer['interests'] ?? [] as $i)<div class="mt-2"><p class="text-brand-body">{{ $i['declaration'] }}</p>@if(!empty($i['mitigation']))<p class="mt-1 text-xs text-brand-muted"><span class="font-medium">Mitigation:</span> {{ $i['mitigation'] }}</p>@endif @if(!empty($i['declared_on']))<p class="text-xs text-brand-muted">Declared {{ \Illuminate\Support\Carbon::parse($i['declared_on'])->format('j M Y') }}</p>@endif</div>@endforeach
        </section>
    </div>
    <section class="mt-8" aria-labelledby="rec-h"><h2 id="rec-h" class="section-title">Records verified by {{ $reviewer['name'] }}</h2>
        @if($verified->isEmpty())<p class="mt-2 text-sm text-brand-muted">No record carries this reviewer's verification yet.</p>@else
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm">@foreach($verified as $r)<li class="py-2"><a href="{{ $r['url'] }}" class="font-medium text-brand-navy no-underline hover:underline">{{ $r['title'] }}</a> <span class="meta">· {{ $r['kind'] }}@if($r['verified_at']) · verified {{ $r['verified_at']->format('j M Y') }}@endif</span></li>@endforeach</ul>
        @endif
    </section>
    <p class="mt-6 text-sm"><a href="{{ route('reviewers') }}" class="text-brand-blue">All reviewers</a> · <a href="{{ route('verification') }}" class="text-brand-blue">How verification works</a></p>
</div>
@endsection
