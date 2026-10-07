@extends('site.layouts.app')
@section('content')
<div class="container-site py-12 max-w-2xl">
    <p class="eyebrow">Your plan</p>
    @if($subscription)
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">You are on {{ $subscription->planName() }}</h1>
    <p class="mt-3 text-brand-body">Thank you. "Follow for daily alerts" is now active on every policy, jurisdiction and obligation page. Follow your first record and the daily email starts the next morning something changes.</p>
    <p class="mt-6 flex flex-wrap gap-2"><a href="{{ route('policies.index') }}" class="btn-primary">Follow your first policy</a><a href="{{ route('following.index') }}" class="btn-secondary">Records you follow</a></p>
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
