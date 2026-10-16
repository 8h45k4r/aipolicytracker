@extends('site.layouts.app')
@section('content')
@php($licence = config('aipolicytracker.data_license'))
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-3 max-w-3xl">
        <p class="eyebrow">Pricing</p>
        <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">The data stays free. Plans pay for the service around it.</h1>
        <p class="mt-3 text-brand-body leading-7">Every policy, jurisdiction, obligation and change record is open under <a href="{{ config('aipolicytracker.data_license_url') }}" rel="license noopener">{{ $licence }}</a> on every plan, commercial use included, and always will be. Builder pays for alerts at volume. Team and Commercial pay for what the open licence does not give: no attribution, freshness in writing, and an agreement a legal team can file.</p>
        @if(!$enabled)<p class="mt-4 rounded-md border border-brand-line bg-brand-paper px-3 py-2 text-sm text-brand-body" role="status">Builder checkout is not open yet. Open is complete and available today, and Team and Commercial are arranged through the <a href="#licence">enquiry form</a>; <a href="{{ route('subscribe.show') }}">subscribe to the weekly digest</a> to hear when Builder opens.</p>@endif
        @if(session('error'))<p class="mt-4 rounded-md border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">{{ session('error') }}</p>@endif
        @if(session('error_detail'))<details class="mt-2 card-flat p-3 text-sm" open><summary class="cursor-pointer font-semibold text-brand-navy">Provider response (visible to admins only)</summary><pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all text-xs text-brand-body">{{ session('error_detail') }}</pre><p class="meta mt-2">Also recorded under <a href="{{ route('backend.admin.billing.index') }}">Admin → Billing</a>, with the checkout attempt.</p></details>@endif
    </div>
    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <section class="card-flat p-5 flex flex-col" aria-labelledby="plan-free" data-tier="open">
            <h2 id="plan-free" class="section-title !text-lg">Open</h2>
            <p class="mt-2 text-3xl font-semibold text-brand-navy">$0</p>
            <p class="mt-1 text-sm text-brand-muted">For anyone reading, citing or building on the records.</p>
            <ul class="mt-4 space-y-2 text-sm text-brand-body">
                <li class="flex gap-2"><span aria-hidden="true">✓</span><span>Commercial use under {{ $licence }}, with attribution</span></li>
                @foreach($features['free'] ?? [] as $f)<li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ $f }}</span></li>@endforeach
            </ul>
            <p class="mt-auto pt-5">@auth<span class="meta">Included with your account</span>@else<a href="{{ route('register') }}" class="btn-secondary">Create a free account</a>@endauth</p>
        </section>

        {{-- Builder is the plan the provider and the account page call Pro: same products,
             same checkout. Each interval keeps its own button so the existing plan keys,
             checkout route and current-plan state are unchanged. --}}
        <section class="card-flat p-5 flex flex-col border-brand-navy" aria-labelledby="plan-builder" data-tier="builder">
            <h2 id="plan-builder" class="section-title !text-lg">Builder</h2>
            @php($monthly = collect($plans)->firstWhere('interval', 'month') ?? collect($plans)->first())
            <p class="mt-2 text-3xl font-semibold text-brand-navy">{{ $monthly ? \App\Services\Billing\PlanCatalog::formatPrice($monthly['price'], $monthly['currency']) : '—' }} @if($monthly)<span class="text-sm font-normal text-brand-muted">per {{ $monthly['interval'] }}</span>@endif</p>
            <p class="mt-1 text-sm text-brand-muted">For one person following many rules. Shown as Pro in your account and on receipts.</p>
            <ul class="mt-4 space-y-2 text-sm text-brand-body"><li class="flex gap-2"><span aria-hidden="true">✓</span><span>Everything in Open</span></li>@foreach($features['pro'] ?? [] as $f)<li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ $f }}</span></li>@endforeach</ul>
            <div class="mt-auto pt-5 space-y-3">
                @forelse($plans as $key => $plan)
                <div>
                    <p class="text-sm text-brand-body">@if($plan['interval'] === 'year')<span class="font-semibold text-brand-navy">{{ \App\Services\Billing\PlanCatalog::formatPrice($plan['price'], $plan['currency']) }}</span> per year, two months free @else Pay monthly @endif</p>
                    <div class="mt-1">
                        @if($current && $current->plan_key === $key)<span class="meta">Your current plan</span>
                        @elseif(!$enabled || empty($plan['product_id']))<span class="btn-secondary opacity-60 cursor-not-allowed" aria-disabled="true">Not yet available</span>
                        @elseif($current)<span class="meta">Change plans from <a href="{{ route('profile.edit') }}">your account</a></span>
                        @elseif($user)<form method="post" action="{{ route('billing.checkout', $key) }}">@csrf<button type="submit" class="btn-primary" data-track="checkout_start" data-plan="{{ $key }}">Subscribe to {{ $plan['name'] }}</button></form>
                        @else<a href="{{ route('login', ['return' => request()->getRequestUri()]) }}" class="btn-primary">Sign in to subscribe</a>
                        @endif
                    </div>
                </div>
                @empty
                <span class="btn-secondary opacity-60 cursor-not-allowed" aria-disabled="true">Not yet available</span>
                @endforelse
            </div>
        </section>

        @foreach($contactPlans as $key => $tier)
        <section class="card-flat p-5 flex flex-col" aria-labelledby="plan-{{ $key }}" data-tier="{{ $key }}">
            <h2 id="plan-{{ $key }}" class="section-title !text-lg">{{ $tier['name'] }}</h2>
            <p class="mt-2 text-3xl font-semibold text-brand-navy">{{ $tier['price'] }} <span class="text-sm font-normal text-brand-muted">{{ $tier['per'] }}</span></p>
            <p class="mt-1 text-sm text-brand-muted">{{ $tier['summary'] }} By written agreement.</p>
            <ul class="mt-4 space-y-2 text-sm text-brand-body">
                <li class="flex gap-2"><span aria-hidden="true">✓</span><span>Everything in Open, commercial use included</span></li>
                @foreach($tier['included'] ?? [] as $f)<li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ $f }}</span></li>@endforeach
                @foreach($tier['agreed'] ?? [] as $f)<li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ $f }} <span class="text-brand-muted">(in the agreement)</span></span></li>@endforeach
            </ul>
            @if(!empty($tier['coming']))
            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-brand-muted">Coming, not built yet</p>
            <ul class="mt-2 space-y-2 text-sm text-brand-muted">@foreach($tier['coming'] as $f)<li class="flex gap-2"><span aria-hidden="true">○</span><span>{{ $f }}</span></li>@endforeach</ul>
            @endif
            <p class="mt-auto pt-5"><a href="{{ route('pricing', ['tier' => $key]) }}#licence" class="btn-secondary" data-track="talk_to_us" data-plan="{{ $key }}">Talk to us</a></p>
        </section>
        @endforeach
    </div>

    <div class="mt-12 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <x-site.licence-enquiry source="pricing" />
            <x-site.faq :items="$seo->faqItems()" />
        </div>
        <aside class="space-y-6" aria-labelledby="billing-h">
            <section class="card-flat p-4 text-sm">
                <h2 id="billing-h" class="font-semibold text-brand-navy">Builder billing</h2>
                <dl class="mt-3 space-y-3 text-brand-body">
                    <div><dt class="font-medium text-brand-navy">Can I cancel?</dt><dd class="mt-1">Yes, from "Manage billing" in your account, at any time. Builder stays on until the end of the period you paid for.</dd></div>
                    <div><dt class="font-medium text-brand-navy">What happens if a payment fails?</dt><dd class="mt-1">You keep access for a short grace period and get an email with a link to update the card. After that the account returns to Open; nothing is deleted.</dd></div>
                    <div><dt class="font-medium text-brand-navy">What if I am refunded?</dt><dd class="mt-1">A full refund, or a payment your bank reverses, ends Builder access from that day. A partial refund does not.</dd></div>
                </dl>
            </section>
        </aside>
    </div>
    <div class="mt-8"><x-site.disclaimer /></div>
</div>
@endsection
