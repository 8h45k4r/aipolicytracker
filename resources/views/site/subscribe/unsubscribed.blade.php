@extends('site.layouts.app')
@section('content')
<div class="container-site py-16 max-w-2xl">
    <p class="eyebrow">Subscription</p>
    @if($pending)
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-navy">Unsubscribe</h1>
        <p class="mt-4 text-brand-body leading-7">Stop sending the weekly AI policy digest to {{ $subscriber->email }}?</p>
        <form method="POST" action="{{ route('subscribe.unsubscribe.post', $subscriber->token) }}" class="mt-6">
            <button type="submit" class="btn-primary">Unsubscribe</button>
        </form>
    @else
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-navy">Unsubscribed</h1>
        <p class="mt-4 text-brand-body leading-7">{{ $subscriber->email }} will not receive further digests. You can subscribe again at any time from the change log; the RSS feed needs no account.</p>
        <p class="mt-6"><a href="{{ route('changes.feed') }}" class="btn-secondary">RSS feed</a></p>
    @endif
</div>
@endsection
