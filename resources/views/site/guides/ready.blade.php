@extends('site.layouts.app')
@section('content')
<div class="container-site py-12 max-w-2xl">
    <p class="eyebrow">Free download</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Your download is ready</h1>
    <p class="mt-2 text-brand-body">{{ $tool->title }} · version {{ $tool->version }} · formats: {{ $links->pluck('label')->implode(', ') }}</p>
    <div class="mt-6 flex flex-col gap-2 max-w-sm">@foreach($links as $l)<a href="{{ $l['url'] }}" class="btn-primary" data-track="file_download" data-track-label="{{ $l['name'] }}">Download {{ $l['label'] }}</a>@endforeach</div>
    <p class="mt-3 meta">Links are personal and expire in 30 minutes; come back to the <a href="{{ $tool->url() }}">tool page</a> to get fresh ones. Every file states its version, date and that it is informational only.</p>
    @if($next)<div class="mt-8 card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">Next step</p><p class="mt-1"><a href="{{ $next->url() }}" class="text-brand-navy">Download the {{ $next->title }} →</a></p></div>@endif
    <p class="mt-6 text-sm"><a href="{{ route('guides.index') }}">All guides and tools</a> · <a href="{{ route('subscribe.show') }}">Weekly policy digest</a></p>
</div>
@endsection
