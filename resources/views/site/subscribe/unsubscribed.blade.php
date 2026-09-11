@extends('site.layouts.app')
@section('content')
<div class="container-site py-16 max-w-2xl">
    <p class="eyebrow">Subscription</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-navy">Unsubscribed</h1>
    <p class="mt-4 text-brand-body leading-7">{{ $subscriber->email }} will not receive further digests. You can subscribe again at any time from the change log; the RSS feed needs no account.</p>
    <p class="mt-6"><a href="{{ route('changes.feed') }}" class="btn-secondary">RSS feed</a></p>
</div>
@endsection
