@extends('backend.layouts.app', ['title' => 'Billing'])
@section('content')
@php($money = fn (array $byCurrency) => $byCurrency === [] ? '—' : collect($byCurrency)->map(fn ($minor, $cur) => \App\Services\Billing\PlanCatalog::formatPrice((int) $minor, $cur).' '.$cur)->implode(' + '))
@php($canUsers = auth()->user()->can('users.manage'))
<x-backend.page-header title="Billing" description="Subscriptions and payments mirrored from Dodo Payments webhooks. Nothing here is edited by hand: to change a customer's plan use the Dodo dashboard and let the webhook update these tables." />

<div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-backend.stat label="Pro subscriptions with access" :value="number_format($summary['monthly'] + $summary['annual'])" :hint="$summary['monthly'].' monthly · '.$summary['annual'].' annual'" :href="route('backend.admin.billing.index', ['status' => 'active'])" />
    <x-backend.stat label="Monthly recurring revenue" :value="$money($summary['mrr'])" hint="Config price of every covering subscription; annual counted as a twelfth. Includes ones set to cancel at period end." />
    <x-backend.stat label="Payments, last 30 days" :value="number_format($summary['payments_count'])" :hint="$money($summary['payments_total']).' collected before refunds'" :href="route('backend.admin.billing.index', ['tab' => 'payments', 'from' => now()->subDays(30)->toDateString()])" />
    <x-backend.stat label="Refunds and lost disputes, last 30 days" :value="$summary['refunds'].' · '.$summary['disputes_lost']" hint="Full or partial refunds · chargebacks lost or accepted" :tone="$summary['refunds'] + $summary['disputes_lost'] ? 'text-state-warn' : null" :href="route('backend.admin.billing.index', ['tab' => 'webhooks', 'outcome' => 'applied', 'from' => now()->subDays(30)->toDateString()])" />
    <x-backend.stat label="Access revoked" :value="number_format($summary['revoked'])" hint="Latest payment refunded in full or charged back" :tone="$summary['revoked'] ? 'text-state-bad' : null" :href="route('backend.admin.billing.index', ['status' => 'revoked'])" />
    <x-backend.stat label="On hold, within grace" :value="number_format($summary['grace'])" hint="Renewal failed; access kept while the customer fixes the payment method" :tone="$summary['grace'] ? 'text-state-warn' : null" :href="route('backend.admin.billing.index', ['status' => 'on_hold'])" />
    <x-backend.stat label="Webhooks that failed" :value="number_format($summary['errors'])" hint="Stored with outcome error; re-apply them from the Webhooks tab" :tone="$summary['errors'] ? 'text-state-bad' : null" :href="route('backend.admin.billing.index', ['tab' => 'webhooks', 'outcome' => 'error'])" />
</div>

<nav class="adm-tabs" aria-label="Billing sections">
    @foreach(\App\Http\Controllers\Backend\Admin\BillingController::TABS as $k => $label)
    <a href="{{ route('backend.admin.billing.index', $k === 'subscriptions' ? [] : ['tab' => $k]) }}" @if($tab === $k) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

@if($tab === 'subscriptions')
<section class="mt-4">
    <h2 class="section-title !text-lg">Subscriptions</h2>
    <p class="mt-2 text-sm">@foreach([...\App\Models\Subscription::STATUSES, 'revoked'] as $s)<a href="{{ request()->fullUrlWithQuery(['status' => $s, 'subs' => null]) }}" class="mr-3 {{ $status === $s ? 'font-semibold' : '' }}" @if($status === $s) aria-current="true" @endif>{{ $s === 'revoked' ? 'access revoked' : str_replace('_', ' ', $s) }} ({{ $byStatus[$s] ?? 0 }})</a>@endforeach <a href="{{ route('backend.admin.billing.index') }}" class="{{ $status ? '' : 'font-semibold' }}">all</a> · checkouts: @forelse($checkouts as $s => $n){{ $s }} {{ $n }}@if(!$loop->last), @endif @empty none @endforelse</p>
    <x-backend.filters :action="route('backend.admin.billing.index')" :filters="$filters" :export="route('backend.admin.billing.export')" placeholder="Email, name, plan or provider id" :total="$subscriptions->total()" noun="subscription">
        @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
    </x-backend.filters>
    @if($subscriptions->isEmpty())<div class="mt-4"><x-site.empty :title="$status || $filters->active() ? 'No subscriptions match these filters' : 'No subscriptions yet'" :reset="$status || $filters->active() ? route('backend.admin.billing.index') : null">Subscriptions appear when a verified webhook from Dodo reports one.</x-site.empty></div>@else
    <div class="table-wrap mt-3"><table>
        <caption class="sr-only">Subscriptions</caption><thead><tr>
            <th scope="col">User</th>
            <x-backend.sort-th key="plan" label="Plan" :filters="$filters" />
            <x-backend.sort-th key="status" label="Status" :filters="$filters" />
            <x-backend.sort-th key="period_end" label="Period end" :filters="$filters" />
            <th scope="col">Last event</th><th scope="col">Provider id</th></tr></thead>
        <tbody>
        @foreach($subscriptions as $s)
        <tr><td>@if($s->user && $canUsers)<a href="{{ route('backend.admin.users.show', $s->user) }}">{{ $s->user->email }}</a>@else{{ $s->user?->email ?? '—' }}@endif</td>
            <td>{{ $s->planName() }}</td>
            <td><x-backend.badge :status="$s->status" />@if($s->cancel_at_period_end) <span class="text-xs text-brand-muted">cancels at period end</span>@endif
                @if($s->revoked_at)<br><x-backend.badge status="failed">access revoked</x-backend.badge> <span class="text-xs text-state-bad">{{ $s->revoked_reason === 'chargeback' ? 'chargeback' : 'refund' }}, {{ $s->revoked_at->format('j M Y') }}</span>@endif</td>
            <td class="whitespace-nowrap">{{ $s->current_period_end?->format('j M Y') ?? '—' }}</td>
            <td class="text-xs">{{ $s->last_event_type ?? '—' }}@if($s->last_event_at) · {{ $s->last_event_at->format('j M Y H:i') }}@endif</td>
            <td class="font-mono text-xs">{{ $s->provider_subscription_id }}<br><a href="{{ route('backend.admin.billing.index', ['tab' => 'payments', 'q' => $s->provider_subscription_id]) }}" class="font-sans">payments</a> · <a href="{{ route('backend.admin.billing.index', ['tab' => 'webhooks', 'q' => $s->provider_subscription_id]) }}" class="font-sans">webhooks</a></td></tr>
        @endforeach
        </tbody></table></div>
    <nav class="mt-3" aria-label="Pagination">{{ $subscriptions->links() }}</nav>
    @endif
</section>

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
    <h2 class="section-title !text-lg">Recent checkout attempts</h2>
    <p class="mt-1 meta">created → returned (customer came back) → completed (webhook applied). An abandoned attempt keeps the provider's answer so a refused session can be diagnosed here.</p>
    <div class="table-wrap mt-3"><table>
        <caption class="sr-only">Recent checkout attempts</caption><thead><tr><th scope="col">When</th><th scope="col">User</th><th scope="col">Plan</th><th scope="col">Status</th><th scope="col">Provider response</th></tr></thead>
        <tbody>
        @forelse($recentCheckouts as $c)
        <tr><td class="whitespace-nowrap">{{ $c->created_at?->format('j M Y H:i') }}</td><td>{{ $c->user?->email ?? '—' }}</td><td>{{ $c->plan_key }}</td><td class="{{ $c->status === 'abandoned' ? 'text-state-bad' : '' }}">{{ $c->status }}</td><td class="text-xs font-mono whitespace-pre-wrap break-all text-brand-muted">{{ $c->error ? \Illuminate\Support\Str::limit($c->error, 600) : '—' }}</td></tr>
        @empty
        <tr><td colspan="5" class="text-brand-muted">No checkout attempts yet.</td></tr>
        @endforelse
        </tbody></table></div>
</section>
@endif

@if($tab === 'payments')
<section class="mt-4">
    <h2 class="section-title !text-lg">Payments</h2>
    <p class="mt-1 meta">Every payment the provider reported (<span class="font-mono">payment.succeeded</span>), with what later happened to it. Refunds and disputes update the row; amounts are as charged, before refunds.</p>
    <x-backend.filters :action="route('backend.admin.billing.index')" :filters="$filters" :export="route('backend.admin.billing.payments.export')" placeholder="Payment or subscription id, account email" :total="$payments->total()" noun="payment">
        <input type="hidden" name="tab" value="payments">
        <div><label for="f-pay-status" class="adm-label">Status</label><select id="f-pay-status" name="status" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option>@foreach(\App\Models\BillingPayment::STATUSES as $s)<option value="{{ $s }}" @selected($paymentStatus === $s)>{{ str_replace('_', ' ', $s) }} ({{ $paymentCounts[$s] ?? 0 }})</option>@endforeach</select></div>
    </x-backend.filters>
    @if($payments->isEmpty())<div class="mt-4"><x-site.empty :title="$paymentStatus || $filters->active() ? 'No payments match these filters' : 'No payments yet'" :reset="$paymentStatus || $filters->active() ? route('backend.admin.billing.index', ['tab' => 'payments']) : null">Payments appear when the provider sends <span class="font-mono">payment.succeeded</span>.</x-site.empty></div>@else
    <div class="table-wrap mt-3"><table>
        <caption class="sr-only">Payments</caption><thead><tr>
            <x-backend.sort-th key="paid" label="Paid" :filters="$filters" />
            <th scope="col">Account</th>
            <x-backend.sort-th key="amount" label="Amount" :filters="$filters" />
            <th scope="col">Refunded</th>
            <x-backend.sort-th key="status" label="Status" :filters="$filters" />
            <th scope="col">Payment id</th><th scope="col">Subscription</th></tr></thead>
        <tbody>
        @foreach($payments as $p)
        @php($sub = $p->provider_subscription_id ? ($paymentSubs[$p->provider_subscription_id] ?? null) : null)
        <tr><td class="whitespace-nowrap">{{ $p->paid_at?->format('j M Y H:i') ?? '—' }}</td>
            <td>@if($sub?->user && $canUsers)<a href="{{ route('backend.admin.users.show', $sub->user) }}">{{ $sub->user->email }}</a>@else{{ $sub?->user?->email ?? '—' }}@endif</td>
            <td class="whitespace-nowrap tabular-nums">{{ $p->total_amount === null ? '—' : \App\Services\Billing\PlanCatalog::formatPrice($p->total_amount, (string) ($p->currency ?: 'USD')).' '.strtoupper((string) $p->currency) }}</td>
            <td class="whitespace-nowrap tabular-nums">{{ $p->refunded_amount ? \App\Services\Billing\PlanCatalog::formatPrice($p->refunded_amount, (string) ($p->currency ?: 'USD')) : '—' }}</td>
            <td><x-backend.badge :status="in_array($p->status, ['refunded', 'dispute_lost'], true) ? 'failed' : (in_array($p->status, ['partially_refunded', 'disputed'], true) ? 'needs_update' : ($p->status === 'succeeded' ? 'ok' : $p->status))">{{ str_replace('_', ' ', $p->status) }}</x-backend.badge>@if($p->dispute_status) <span class="text-xs text-brand-muted">{{ $p->dispute_status }}</span>@endif</td>
            <td class="font-mono text-xs">{{ $p->provider_payment_id }}</td>
            <td class="font-mono text-xs">@if($p->provider_subscription_id)<a href="{{ route('backend.admin.billing.index', ['q' => $p->provider_subscription_id]) }}">{{ $p->provider_subscription_id }}</a>@if($sub) <span class="font-sans">· {{ $sub->planName() }}</span>@endif @else — @endif</td></tr>
        @endforeach
        </tbody></table></div>
    <nav class="mt-3" aria-label="Pagination">{{ $payments->links() }}</nav>
    @endif
</section>
@endif

@if($tab === 'webhooks')
<section class="mt-4">
    <h2 class="section-title !text-lg">Received webhooks</h2>
    <p class="mt-1 meta">Every signed delivery, stored once by its event id. A retry of an applied event is acknowledged as a duplicate without a new row. An event whose processing failed keeps its error; re-apply runs it again from the stored payload with the same event id, as a provider retry would.</p>
    <p class="mt-2 text-sm">@foreach([...\App\Models\BillingEvent::OUTCOMES, 'pending'] as $o)<a href="{{ route('backend.admin.billing.index', ['tab' => 'webhooks', 'outcome' => $o]) }}" class="mr-3 {{ $outcome === $o ? 'font-semibold' : '' }} {{ $o === 'error' && ($outcomeCounts[$o] ?? 0) ? 'text-state-bad' : '' }}" @if($outcome === $o) aria-current="true" @endif>{{ $o }} ({{ $outcomeCounts[$o] ?? 0 }})</a>@endforeach <a href="{{ route('backend.admin.billing.index', ['tab' => 'webhooks']) }}" class="{{ $outcome ? '' : 'font-semibold' }}">all</a></p>
    <x-backend.filters :action="route('backend.admin.billing.index')" :filters="$filters" placeholder="Event type, event id or subscription id" :total="$events->total()" noun="event">
        <input type="hidden" name="tab" value="webhooks">
        <div><label for="f-outcome" class="adm-label">Outcome</label><select id="f-outcome" name="outcome" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option>@foreach([...\App\Models\BillingEvent::OUTCOMES, 'pending'] as $o)<option value="{{ $o }}" @selected($outcome === $o)>{{ $o }}</option>@endforeach</select></div>
    </x-backend.filters>
    @if($events->isEmpty())<div class="mt-4"><x-site.empty :title="$outcome || $filters->active() ? 'No webhooks match these filters' : 'No webhooks received yet'" :reset="$outcome || $filters->active() ? route('backend.admin.billing.index', ['tab' => 'webhooks']) : null">Webhooks appear here once the provider sends a signed delivery to {{ route('billing.webhook') }}.</x-site.empty></div>@else
    <div class="table-wrap mt-3"><table>
        <caption class="sr-only">Received webhooks</caption><thead><tr>
            <x-backend.sort-th key="received" label="Received" :filters="$filters" />
            <x-backend.sort-th key="type" label="Type" :filters="$filters" />
            <th scope="col">Subscription</th>
            <x-backend.sort-th key="outcome" label="Outcome" :filters="$filters" />
            <th scope="col">Error</th><th scope="col">Actions</th></tr></thead>
        <tbody>
        @foreach($events as $e)
        <tr><td class="whitespace-nowrap">{{ $e->received_at?->format('j M Y H:i:s') }}<br><span class="font-mono text-xs text-brand-muted">{{ $e->event_id }}</span></td>
            <td>{{ $e->event_type }}</td>
            <td class="font-mono text-xs">@if($e->provider_subscription_id)<a href="{{ route('backend.admin.billing.index', ['q' => $e->provider_subscription_id]) }}">{{ $e->provider_subscription_id }}</a>@else — @endif</td>
            <td><x-backend.badge :status="match ($e->outcome) { 'applied' => 'ok', 'error' => 'failed', 'stale' => 'needs_update', null => 'running', default => 'no' }">{{ $e->outcome ?? 'pending' }}</x-backend.badge></td>
            <td class="text-xs font-mono whitespace-pre-wrap break-all {{ $e->outcome === 'error' ? 'text-state-bad' : 'text-brand-muted' }}">{{ $e->error ? \Illuminate\Support\Str::limit($e->error, 2000) : '—' }}</td>
            <td class="whitespace-nowrap">@if($e->outcome === 'error')<form method="post" action="{{ route('backend.admin.billing.events.reapply', $e) }}" class="inline" data-confirm="Apply {{ $e->event_type }} again from its stored payload? It runs exactly as a provider retry would and may change this customer's access.">@csrf<button type="submit" class="btn-secondary !min-h-0 !py-1 !px-2 text-xs">Re-apply</button></form>@else — @endif</td></tr>
        @endforeach
        </tbody></table></div>
    <nav class="mt-3" aria-label="Pagination">{{ $events->links() }}</nav>
    @endif
</section>
@endif

@if($tab === 'quota')
<section class="mt-4">
    <h2 class="section-title !text-lg">Free-tier quota</h2>
    <p class="mt-1 meta">The free plan allows {{ $quota['limit'] }} watches. An account over the allowance keeps every watch; its daily alert reads the oldest {{ $quota['limit'] }}. Checkout is {{ $quota['selling'] ? 'on, so the allowance applies now' : 'off, so every signed-in account has the Pro allowance until selling starts' }}.</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-backend.stat label="Accounts with no watches" :value="number_format($quota['buckets']['none'])" />
        <x-backend.stat :label="'Accounts with 1–'.$quota['limit'].' watches'" :value="number_format($quota['buckets']['within'])" />
        <x-backend.stat :label="'Accounts with '.($quota['limit'] + 1).'+ watches'" :value="number_format($quota['buckets']['over'])" />
        <x-backend.stat :label="$quota['selling'] ? 'Above the free limit now' : 'Above the free limit once selling starts'" :value="number_format($quota['over_free_count'])" hint="Over the allowance with no paid subscription covering them" :tone="$quota['selling'] && $quota['over_free_count'] ? 'text-state-warn' : null" :href="$canUsers ? route('backend.admin.users.index') : null" />
    </div>
    @if($quota['over_free']->isEmpty())<div class="mt-4"><x-site.empty title="No account is above the free limit">Every account with more than {{ $quota['limit'] }} watches has a paid subscription, or none has that many.</x-site.empty></div>@else
    <div class="table-wrap mt-4"><table>
        <caption class="sr-only">Accounts above the free watch limit without a paid subscription</caption><thead><tr><th scope="col">Account</th><th scope="col">Watches</th><th scope="col">Joined</th></tr></thead>
        <tbody>
        @foreach($quota['over_free'] as $row)
        <tr><td>@if($canUsers)<a href="{{ route('backend.admin.users.show', $row['user']) }}">{{ $row['user']->email }}</a>@else{{ $row['user']->email }}@endif</td><td class="tabular-nums">{{ $row['watches'] }}</td><td class="whitespace-nowrap">{{ $row['user']->created_at?->format('j M Y') ?? '—' }}</td></tr>
        @endforeach
        </tbody></table></div>
    @if($quota['over_free_count'] > $quota['list_cap'])<p class="mt-2 meta">Showing the {{ $quota['list_cap'] }} with the most watches.</p>@endif
    @endif
    @if($canUsers)<p class="mt-3 text-sm"><a href="{{ route('backend.admin.users.index') }}">Open users and roles</a></p>@endif
</section>
@endif
@endsection
