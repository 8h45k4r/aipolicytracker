@extends('site.layouts.app')
@section('content')
<div class="container-site py-16 max-w-xl text-center">
    <p class="eyebrow">{{ $plan ? 'Secure checkout' : 'Billing portal' }}</p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $plan ? 'Taking you to the payment page' : 'Opening your billing portal' }}</h1>
    <p class="mt-3 text-brand-body">@if($plan)You are subscribing to <strong>{{ $plan['name'] }}</strong> ({{ \App\Services\Billing\PlanCatalog::formatPrice($plan['price'], $plan['currency']) }} per {{ $plan['interval'] }}). @endif Payment is handled by Dodo Payments, our merchant of record; card details never touch this site.</p>
    <p class="mt-6"><a href="{{ $url }}" class="btn-primary" rel="noopener">Continue</a></p>
    <p class="mt-3 meta">If nothing happens within a few seconds, use the button above.</p>
    <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">window.setTimeout(function () { window.location.replace(@json($url)); }, 800);</script>
</div>
@endsection
