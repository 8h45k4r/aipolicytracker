@extends('site.layouts.app')
@section('content')
<div class="container-site py-12 max-w-2xl">
    <p class="eyebrow">Your plan</p>
    @if($subscription)
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">You are on {{ $subscription->planName() }}</h1>
    <p class="mt-3 text-brand-body">Thank you. Daily alerts, synced saved records, full change history and the higher API quota are now available on your account.</p>
    <p class="mt-6 flex flex-wrap gap-2"><a href="{{ route('profile.edit') }}" class="btn-primary">Open your account</a><a href="{{ route('policies.index') }}" class="btn-secondary">Browse policies</a></p>
    @elseif($failed)
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">The payment was not completed</h1>
    <p class="mt-3 text-brand-body">No charge was made and your account stays on Free. You can try again whenever you like.</p>
    <p class="mt-6"><a href="{{ route('pricing') }}" class="btn-primary">Back to pricing</a></p>
    @else
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Confirming your subscription</h1>
    <p class="mt-3 text-brand-body">We are waiting for the payment provider to confirm the {{ $plan['name'] ?? 'Pro' }} subscription. This usually takes a few seconds; refresh this page or open your account to see the plan once it is active.</p>
    <p class="mt-6 flex flex-wrap gap-2"><a href="{{ route('billing.return', $checkout) }}" class="btn-primary">Refresh</a><a href="{{ route('profile.edit') }}" class="btn-secondary">Open your account</a></p>
    <p class="mt-3 meta">If the plan does not appear within a few minutes, reply to your receipt email and we will sort it out.</p>
    @endif
</div>
@endsection
