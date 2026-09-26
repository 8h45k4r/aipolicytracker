@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI policy digest archive</h1>
    <p class="mt-2 text-brand-body">Every issue of the weekly digest, as sent: the dated, source-backed changes to AI law and guidance that week, and the application dates coming up. <a href="{{ route('subscribe.show') }}" class="text-brand-blue hover:underline">Subscribe</a> to receive it, or follow the <a href="{{ route('updates.index') }}" class="text-brand-blue hover:underline">updates hub</a> between issues.</p>
    @if($issues->isEmpty())
        <p class="mt-8 text-sm text-brand-muted">No issue has been archived yet. Issues appear here from the first digest sent after this archive was added.</p>
    @else
    <ol class="mt-6 divide-y divide-brand-line border-y border-brand-line">
        @foreach($issues as $issue)
        <li class="py-4">
            <a href="{{ $issue->url() }}" class="font-display text-lg font-semibold text-brand-navy no-underline hover:underline">AI policy digest, {{ $issue->sent_on->format('j F Y') }}</a>
            <p class="mt-1 text-sm text-brand-muted">Week to {{ $issue->period_end->format('j M Y') }} · {{ count($issue->change_ids ?: []) }} {{ \Illuminate\Support\Str::plural('change', count($issue->change_ids ?: [])) }}@if(count($issue->deadline_ids ?: [])) · {{ count($issue->deadline_ids) }} upcoming {{ \Illuminate\Support\Str::plural('date', count($issue->deadline_ids)) }}@endif</p>
        </li>
        @endforeach
    </ol>
    <nav class="mt-6" aria-label="Pagination">{{ $issues->links() }}</nav>
    @endif
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
