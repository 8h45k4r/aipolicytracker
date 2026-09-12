@extends('backend.layouts.app', ['title' => 'Billing'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Billing</h1>
<p class="mt-1 meta">Subscriptions mirrored from Dodo Payments webhooks. Nothing here is edited by hand: to change a customer's plan use the Dodo dashboard and let the webhook update this table.</p>
<section class="mt-6 card-flat p-5">
    <h2 class="section-title !text-lg">Setup</h2>
    <dl class="mt-3 text-sm divide-y divide-brand-line">
        <div class="py-2 flex justify-between gap-4"><dt class="text-brand-muted">Checkout</dt><dd>{{ $setup['enabled'] ? 'on' : 'off' }} <span class="meta">(from {{ $setup['enabled_source'] === 'setting' ? 'Settings' : 'BILLING_ENABLED' }})</span></dd></div>
        <div class="py-2 flex justify-between gap-4"><dt class="text-brand-muted">Environment</dt><dd class="font-mono">{{ $setup['environment'] }}</dd></div>
        <div class="py-2 flex justify-between gap-4"><dt class="text-brand-muted">API key</dt><dd>{{ $setup['api_key'] ? 'configured' : 'missing' }}</dd></div>
        <div class="py-2 flex justify-between gap-4"><dt class="text-brand-muted">Webhook secret</dt><dd>{{ $setup['webhook_secret'] ? 'configured' : 'missing' }}</dd></div>
        <div class="py-2 flex justify-between gap-4"><dt class="text-brand-muted">Webhook URL to register in Dodo</dt><dd class="font-mono break-all">{{ $setup['webhook_url'] }}</dd></div>
        @foreach($setup['plans'] as $key => $plan)
        <div class="py-2 flex justify-between gap-4"><dt class="text-brand-muted">{{ $plan['name'] }} ({{ $key }})</dt><dd class="text-right">{{ \App\Services\Billing\PlanCatalog::formatPrice($plan['price'], $plan['currency']) }}/{{ $plan['interval'] }} · product <span class="font-mono">{{ $plan['product_id'] ?: 'not set' }}</span>
            @if(isset($check[$key]))<br><span class="{{ $check[$key]['ok'] ? 'text-state-good' : 'text-state-bad' }}">{{ $check[$key]['note'] }}@if(!empty($check[$key]['remote'])) (provider: {{ $check[$key]['remote']['price'] }} {{ $check[$key]['remote']['currency'] }} / {{ $check[$key]['remote']['interval'] ?? '?' }})@endif</span>@endif</dd></div>
        @endforeach
    </dl>
    <div class="mt-4 flex flex-wrap gap-2">
        <form method="post" action="{{ route('backend.admin.billing.provision') }}">@csrf<button type="submit" class="btn-primary" @disabled(!$setup['api_key'])>Provision webhook and products</button></form>
        <form method="post" action="{{ route('backend.admin.billing.check') }}">@csrf<button type="submit" class="btn-secondary">Check products against the provider</button></form>
        <form method="post" action="{{ route('backend.admin.billing.probe') }}">@csrf<button type="submit" class="btn-secondary" @disabled(!$setup['api_key'])>Can we sell right now?</button></form>
    </div>
    @if($probe)
    <div class="mt-3 rounded-sm border px-3 py-2 text-sm {{ $probe['ok'] ? 'border-state-good/30 bg-state-goodbg text-state-good' : 'border-state-bad/30 bg-state-badbg text-state-bad' }}" role="status">
        <p class="font-semibold">{{ $probe['ok'] ? 'Yes: the provider opened a checkout session' : 'No: the provider refused to open a checkout session' }} ({{ $probe['plan'] }}, {{ $probe['environment'] }})</p>
        <pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all text-xs">{{ $probe['detail'] }}</pre>
        @unless($probe['ok'])<p class="mt-2 text-xs">A refusal here is the provider's answer, not this site's: an unverified business, a product that cannot sell in this environment, or a key without payment permission. Nothing was charged.</p>@endunless
    </div>
    @endif
    <p class="meta mt-2">Provisioning needs only the API key: it registers <span class="font-mono">{{ $setup['webhook_url'] }}</span> for every subscription and payment event (reusing an endpoint with that URL), creates one recurring product per plan (reusing products with the same name) and stores the signing secret and product ids encrypted. Repeat it after switching the environment to live_mode.</p>
</section>
<section class="mt-6 card-flat p-5">
    <h2 class="section-title !text-lg">Subscriptions</h2>
    <p class="mt-2 text-sm">@foreach(\App\Models\Subscription::STATUSES as $s)<a href="{{ route('backend.admin.billing.index', ['status' => $s]) }}" class="mr-3 {{ $status === $s ? 'font-semibold' : '' }}">{{ $s }} ({{ $byStatus[$s] ?? 0 }})</a>@endforeach <a href="{{ route('backend.admin.billing.index') }}" class="{{ $status ? '' : 'font-semibold' }}">all</a> · checkouts: @foreach($checkouts as $s => $n){{ $s }} {{ $n }}@if(!$loop->last), @endif @endforeach</p>
    <div class="mt-3 overflow-x-auto"><table class="w-full text-sm">
        <thead><tr class="text-left text-brand-muted"><th class="py-1 pr-3">User</th><th class="py-1 pr-3">Plan</th><th class="py-1 pr-3">Status</th><th class="py-1 pr-3">Period end</th><th class="py-1 pr-3">Last event</th><th class="py-1">Provider id</th></tr></thead>
        <tbody class="divide-y divide-brand-line">
        @forelse($subscriptions as $s)
        <tr><td class="py-1.5 pr-3">{{ $s->user?->email ?? '—' }}</td><td class="py-1.5 pr-3">{{ $s->planName() }}</td><td class="py-1.5 pr-3">{{ $s->status }}@if($s->cancel_at_period_end) (cancels at period end)@endif</td><td class="py-1.5 pr-3">{{ $s->current_period_end?->format('j M Y') ?? '—' }}</td><td class="py-1.5 pr-3">{{ $s->last_event_type }} · {{ $s->last_event_at?->format('j M Y H:i') }}</td><td class="py-1.5 font-mono text-xs">{{ $s->provider_subscription_id }}</td></tr>
        @empty
        <tr><td colspan="6" class="py-3 text-brand-muted">No subscriptions yet.</td></tr>
        @endforelse
        </tbody></table></div>
    <div class="mt-3">{{ $subscriptions->links() }}</div>
</section>
<section class="mt-6 card-flat p-5">
    <h2 class="section-title !text-lg">Recent checkout attempts</h2>
    <p class="mt-1 meta">created → returned (customer came back) → completed (webhook applied). An abandoned attempt keeps the provider's answer so a refused session can be diagnosed here.</p>
    <div class="mt-3 overflow-x-auto"><table class="w-full text-sm">
        <thead><tr class="text-left text-brand-muted"><th class="py-1 pr-3">When</th><th class="py-1 pr-3">User</th><th class="py-1 pr-3">Plan</th><th class="py-1 pr-3">Status</th><th class="py-1">Provider response</th></tr></thead>
        <tbody class="divide-y divide-brand-line">
        @forelse($recentCheckouts as $c)
        <tr><td class="py-1.5 pr-3 whitespace-nowrap">{{ $c->created_at?->format('j M Y H:i') }}</td><td class="py-1.5 pr-3">{{ $c->user?->email ?? '—' }}</td><td class="py-1.5 pr-3">{{ $c->plan_key }}</td><td class="py-1.5 pr-3 {{ $c->status === 'abandoned' ? 'text-state-bad' : '' }}">{{ $c->status }}</td><td class="py-1.5 text-xs font-mono whitespace-pre-wrap break-all text-brand-muted">{{ $c->error ? \Illuminate\Support\Str::limit($c->error, 600) : '—' }}</td></tr>
        @empty
        <tr><td colspan="5" class="py-3 text-brand-muted">No checkout attempts yet.</td></tr>
        @endforelse
        </tbody></table></div>
</section>
<section class="mt-6 card-flat p-5">
    <h2 class="section-title !text-lg">Received webhooks</h2>
    <div class="mt-3 overflow-x-auto"><table class="w-full text-sm">
        <thead><tr class="text-left text-brand-muted"><th class="py-1 pr-3">Received</th><th class="py-1 pr-3">Type</th><th class="py-1 pr-3">Subscription</th><th class="py-1 pr-3">Outcome</th><th class="py-1">Error</th></tr></thead>
        <tbody class="divide-y divide-brand-line">
        @forelse($events as $e)
        <tr><td class="py-1.5 pr-3 whitespace-nowrap">{{ $e->received_at?->format('j M Y H:i:s') }}</td><td class="py-1.5 pr-3">{{ $e->event_type }}</td><td class="py-1.5 pr-3 font-mono text-xs">{{ $e->provider_subscription_id ?? '—' }}</td><td class="py-1.5 pr-3 {{ $e->outcome === 'error' ? 'text-state-bad' : '' }}">{{ $e->outcome ?? 'pending' }}</td><td class="py-1.5 text-xs text-brand-muted">{{ \Illuminate\Support\Str::limit($e->error, 120) }}</td></tr>
        @empty
        <tr><td colspan="5" class="py-3 text-brand-muted">No webhooks received yet.</td></tr>
        @endforelse
        </tbody></table></div>
    <div class="mt-3">{{ $events->links() }}</div>
</section>
@endsection
