@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-3 max-w-3xl">
        <p class="eyebrow">Pricing</p>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">The data stays free. Pro pays for the alerts.</h1>
        <p class="mt-3 text-brand-body leading-7">Every policy, jurisdiction, obligation and change record is open under CC BY 4.0 and always will be. Pro is for teams that need to know the day something changes, not the week after.</p>
        @if(!$enabled)<p class="mt-4 rounded-sm border border-brand-line bg-brand-paper px-3 py-2 text-sm text-brand-body" role="status">Paid plans are not on sale yet. The Free plan is complete and available today; <a href="{{ route('subscribe.show') }}">subscribe to the weekly digest</a> to hear when Pro opens.</p>@endif
        @if(session('error'))<p class="mt-4 rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">{{ session('error') }}</p>@endif
    </div>
    <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <section class="card-flat p-5 flex flex-col" aria-labelledby="plan-free">
            <h2 id="plan-free" class="section-title !text-lg">{{ $free['name'] ?? 'Free' }}</h2>
            <p class="mt-2 text-3xl font-semibold text-brand-navy">$0</p>
            <ul class="mt-4 space-y-2 text-sm text-brand-body">@foreach($features['free'] ?? [] as $f)<li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ $f }}</span></li>@endforeach</ul>
            <p class="mt-auto pt-5">@auth<span class="meta">Included with your account</span>@else<a href="{{ route('register') }}" class="btn-secondary">Create a free account</a>@endauth</p>
        </section>
        @foreach($plans as $key => $plan)
        <section class="card-flat p-5 flex flex-col {{ $loop->first ? 'border-brand-navy' : '' }}" aria-labelledby="plan-{{ $key }}">
            <h2 id="plan-{{ $key }}" class="section-title !text-lg">{{ $plan['name'] }}</h2>
            <p class="mt-2 text-3xl font-semibold text-brand-navy">{{ \App\Services\Billing\PlanCatalog::formatPrice($plan['price'], $plan['currency']) }} <span class="text-sm font-normal text-brand-muted">per {{ $plan['interval'] }}@if($plan['interval'] === 'year'), two months free @endif</span></p>
            <ul class="mt-4 space-y-2 text-sm text-brand-body"><li class="flex gap-2"><span aria-hidden="true">✓</span><span>Everything in Free</span></li>@foreach($features['pro'] ?? [] as $f)<li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ $f }}</span></li>@endforeach</ul>
            <div class="mt-auto pt-5">
                @if($current && $current->plan_key === $key)<span class="meta">Your current plan</span>
                @elseif(!$enabled || empty($plan['product_id']))<span class="btn-secondary opacity-60 cursor-not-allowed" aria-disabled="true">Not yet available</span>
                @elseif($current)<span class="meta">Change plans from <a href="{{ route('profile.edit') }}">your account</a></span>
                @elseif($user)<form method="post" action="{{ route('billing.checkout', $key) }}">@csrf<button type="submit" class="btn-primary" data-track="checkout_start" data-plan="{{ $key }}">Subscribe to {{ $plan['name'] }}</button></form>
                @else<a href="{{ route('login') }}" class="btn-primary">Sign in to subscribe</a>
                @endif
            </div>
        </section>
        @endforeach
    </div>
    <section class="mt-12 max-w-3xl" aria-labelledby="faq-h">
        <h2 id="faq-h" class="section-title">Questions</h2>
        <dl class="mt-4 space-y-4 text-sm text-brand-body">
            <div><dt class="font-semibold text-brand-navy">Who takes the payment?</dt><dd class="mt-1">Dodo Payments, acting as merchant of record. It handles cards, local payment methods, tax and invoices; this site never stores card data.</dd></div>
            <div><dt class="font-semibold text-brand-navy">Can I cancel?</dt><dd class="mt-1">Yes, from "Manage billing" in your account, at any time. Pro stays on until the end of the period you paid for.</dd></div>
            <div><dt class="font-semibold text-brand-navy">What happens if a payment fails?</dt><dd class="mt-1">You keep access for a short grace period and get an email with a link to update the card. After that the account returns to Free; nothing is deleted.</dd></div>
            <div><dt class="font-semibold text-brand-navy">Is the data licence different for Pro?</dt><dd class="mt-1">No. The records stay CC BY 4.0 for everyone. Pro pays for the service around them: alerts, sync, history and quota.</dd></div>
        </dl>
    </section>
    <div class="mt-8"><x-site.disclaimer /></div>
</div>
@endsection
