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

    {{-- Live data summary —  trust signal showing what the dataset actually contains --}}
    <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-brand-body">
        <span><strong class="text-brand-navy">{{ number_format($stats['jurisdictions'] ?? 0) }}</strong> jurisdictions</span>
        <span><strong class="text-brand-navy">{{ number_format($stats['policies'] ?? 0) }}</strong> instruments</span>
        <span><strong class="text-brand-navy">{{ number_format($stats['obligations'] ?? 0) }}</strong> obligations</span>
        <span><strong class="text-brand-navy">{{ number_format($stats['controls'] ?? 0) }}</strong> controls</span>
        <span><strong class="text-brand-navy">{{ number_format($stats['changes'] ?? 0) }}</strong> changes tracked</span>
        @if(($stats['verified'] ?? 0) > 0)<span><strong class="text-brand-navy">{{ number_format($stats['verified']) }}</strong> verified against official sources</span>@endif
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

    {{-- Feature comparison — structured for researchers and procurement --}}
    <section class="mt-12" aria-labelledby="compare-h">
        <h2 id="compare-h" class="section-title">What each plan includes</h2>
        <p class="mt-2 text-sm text-brand-body">Every capability below is live and working today. The tools, exports and API serve the same data on every plan; paid plans add delivery and volume.</p>
        <div class="mt-4 overflow-x-auto -mx-4 px-4">
            <table class="w-full text-sm text-brand-body border-collapse">
                <thead>
                    <tr class="border-b border-brand-line text-left">
                        <th class="py-2 pr-4 font-semibold text-brand-navy">Capability</th>
                        <th class="py-2 px-4 font-semibold text-brand-navy text-center">Open</th>
                        <th class="py-2 px-4 font-semibold text-brand-navy text-center">Builder</th>
                        <th class="py-2 px-4 font-semibold text-brand-navy text-center">Team / Commercial</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-line/50">
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('policies.index') }}">Policy, jurisdiction, obligation and control records</a></td>
                        <td class="py-2 px-4 text-center">All</td>
                        <td class="py-2 px-4 text-center">All</td>
                        <td class="py-2 px-4 text-center">All</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('tools.applicability') }}">Applicability screening</a></td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('compare.index') }}">Jurisdiction comparisons</a></td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('templates.index') }}">Templates library</a> (individual)</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4">Templates bundle (all formats, one ZIP)</td>
                        <td class="py-2 px-4 text-center">—</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('open-data') }}">Bulk CSV, NDJSON and JSON exports</a></td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('developers') }}">Read-only API</a></td>
                        <td class="py-2 px-4 text-center">120 req/min</td>
                        <td class="py-2 px-4 text-center">600 req/min</td>
                        <td class="py-2 px-4 text-center">600 req/min</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('embed.index') }}">Embeddable widgets</a> and <a href="{{ route('embed.index') }}">status badges</a></td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('mcp') }}">MCP server</a> (run on your own machine)</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4"><a href="{{ route('subscribe.show') }}">Weekly digest</a></td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4">Records you can watch</td>
                        <td class="py-2 px-4 text-center">3</td>
                        <td class="py-2 px-4 text-center">500</td>
                        <td class="py-2 px-4 text-center">500</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4">Daily change and deadline alerts (email)</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4">Alerts to Slack, webhook or private RSS</td>
                        <td class="py-2 px-4 text-center">—</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4">Saved applicability profiles (change-impact alerts)</td>
                        <td class="py-2 px-4 text-center">—</td>
                        <td class="py-2 px-4 text-center">✓</td>
                        <td class="py-2 px-4 text-center">✓</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4">Attribution-free licence</td>
                        <td class="py-2 px-4 text-center">—</td>
                        <td class="py-2 px-4 text-center">—</td>
                        <td class="py-2 px-4 text-center">By agreement</td>
                    </tr>
                    <tr>
                        <td class="py-2 pr-4">Freshness commitment (SLA)</td>
                        <td class="py-2 px-4 text-center">—</td>
                        <td class="py-2 px-4 text-center">—</td>
                        <td class="py-2 px-4 text-center">By agreement</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- Trust and methodology links — so researchers know how to verify claims --}}
    <section class="mt-10" aria-labelledby="trust-h">
        <h2 id="trust-h" class="section-title">How the records are kept and checked</h2>
        <p class="mt-2 text-sm text-brand-body leading-7">The data is only worth paying for if you can check it. These pages explain who verifies the records and how.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('methodology') }}" class="card-flat p-4 hover:border-brand-navy/30 transition-colors">
                <p class="font-semibold text-brand-navy text-sm">Methodology</p>
                <p class="mt-1 text-xs text-brand-muted">Review statuses, source tiers, independent checks and taxonomy.</p>
            </a>
            <a href="{{ route('independence') }}" class="card-flat p-4 hover:border-brand-navy/30 transition-colors">
                <p class="font-semibold text-brand-navy text-sm">Independence</p>
                <p class="mt-1 text-xs text-brand-muted">Who checks the records, the commercial relationship and what keeps them separate.</p>
            </a>
            <a href="{{ route('freshness') }}" class="card-flat p-4 hover:border-brand-navy/30 transition-colors">
                <p class="font-semibold text-brand-navy text-sm">Freshness</p>
                <p class="mt-1 text-xs text-brand-muted">How quickly new laws appear here, measured against a public target of {{ config('changedesk.target_days') }} days.</p>
            </a>
            <a href="{{ route('coverage') }}" class="card-flat p-4 hover:border-brand-navy/30 transition-colors">
                <p class="font-semibold text-brand-navy text-sm">Coverage</p>
                <p class="mt-1 text-xs text-brand-muted">How complete the records are: fields, sources and verification status.</p>
            </a>
            <a href="{{ route('corrections') }}" class="card-flat p-4 hover:border-brand-navy/30 transition-colors">
                <p class="font-semibold text-brand-navy text-sm">Corrections</p>
                <p class="mt-1 text-xs text-brand-muted">Every correction decided and every one turned down, in public.</p>
            </a>
            <a href="{{ route('funding') }}" class="card-flat p-4 hover:border-brand-navy/30 transition-colors">
                <p class="font-semibold text-brand-navy text-sm">Funding</p>
                <p class="mt-1 text-xs text-brand-muted">Where the money comes from and the rules that keep it away from the data.</p>
            </a>
        </div>
    </section>

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
