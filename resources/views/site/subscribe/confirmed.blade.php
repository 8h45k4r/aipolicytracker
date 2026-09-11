@extends('site.layouts.app')
@section('content')
<div class="container-site py-16 max-w-2xl">
    <p class="eyebrow">Subscription</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-navy">You are subscribed</h1>
    <p class="mt-4 text-brand-body leading-7">The weekly AI policy digest will arrive at <strong>{{ $subscriber->email }}</strong>{{ ($subscriber->topics && !in_array('all', $subscriber->topics, true)) ? ' for '.implode(', ', $subscriber->topics) : '' }}. Every item links to its official source, and every email carries a one-click unsubscribe.</p>
    <p class="mt-6"><a href="{{ route('changes.index') }}" class="btn-primary">Read the change log now</a></p>
</div>
@endsection
