@extends('backend.layouts.app', ['title' => 'Alerts'])
@section('content')
@php($canManage = auth()->user()->can('subscribers.manage'))
@php($canUsers = auth()->user()->can('users.manage'))
<x-backend.page-header title="Alerts and watches" description="Watches, alert channels, Slack and webhook deliveries, and the consent log. Daily emails are sent by the alerts:send job; Slack and webhook deliveries are retried hourly with backoff, up to five attempts." />

<div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-backend.stat label="Accounts with watches" :value="number_format($cards['accounts'])" :hint="number_format($cards['watches']->sum()).' watches in all'" />
    <div class="card-flat p-4">
        <p class="text-xs font-medium text-brand-muted">Watches by type</p>
        @if($cards['watches']->isEmpty())<p class="mt-1 text-2xl font-semibold text-brand-navy">0</p>@else
        <dl class="mt-1 grid grid-cols-2 gap-x-3 text-sm">@foreach($cards['watches'] as $type => $n)<dt class="text-brand-muted">{{ \App\Models\Follow::typeLabel($type) }}</dt><dd class="text-right tabular-nums font-semibold text-brand-navy" data-watch-type="{{ $type }}">{{ number_format($n) }}</dd>@endforeach</dl>
        @endif
    </div>
    <x-backend.stat label="Applicability profiles saved" :value="number_format($cards['profiles'])" :hint="'by '.number_format($cards['profile_accounts']).' '.\Illuminate\Support\Str::plural('account', $cards['profile_accounts'])" />
    <div class="card-flat p-4">
        <p class="text-xs font-medium text-brand-muted">Channels (enabled of total)</p>
        @if($cards['channels']->isEmpty())<p class="mt-1 text-2xl font-semibold text-brand-navy">0</p><p class="mt-1 text-xs text-brand-muted">No account has added a channel; alerts go to the inbox.</p>@else
        <dl class="mt-1 grid grid-cols-2 gap-x-3 text-sm">@foreach($cards['channels'] as $kind => $c)<dt class="text-brand-muted">{{ \App\Models\AlertChannel::KINDS[$kind] ?? $kind }}</dt><dd class="text-right tabular-nums font-semibold text-brand-navy" data-channel-kind="{{ $kind }}">{{ $c['enabled'] }} / {{ $c['total'] }}</dd>@endforeach</dl>
        @endif
    </div>
    @php($run = $cards['last_send'])
    <x-backend.stat label="Last alerts:send run" :value="$run ? $run->started_at->format('j M H:i') : 'never'" :hint="$run ? ($run->finished_at === null ? 'still running' : ($run->succeeded() ? 'succeeded' : 'failed, exit '.$run->exit_code)).' · '.$run->trigger : 'The scheduler has not run it yet'" :tone="$run && $run->finished_at && ! $run->succeeded() ? 'text-state-bad' : null" :href="auth()->user()->can('jobs.run') ? route('backend.admin.jobs') : null" />
    <x-backend.stat label="Deliveries today" :value="number_format($cards['emails_today'] + $cards['channel_sent_today'])" :hint="$cards['emails_today'].' alert '.\Illuminate\Support\Str::plural('email', $cards['emails_today']).' · '.$cards['channel_sent_today'].' Slack or webhook'" :href="route('backend.admin.alerts.index', ['status' => 'sent', 'from' => today()->toDateString()])" />
    <x-backend.stat label="Deliveries failed, last 7 days" :value="number_format($cards['failed_week'])" hint="Five attempts used up, or the channel was disabled" :tone="$cards['failed_week'] ? 'text-state-bad' : null" :href="route('backend.admin.alerts.index', ['status' => 'failed'])" />
</div>

<section class="mt-8" id="deliveries">
    <h2 class="section-title !text-lg">Slack and webhook deliveries</h2>
    <nav class="adm-tabs" aria-label="Delivery status">
        <a href="{{ route('backend.admin.alerts.index') }}" @if(! $status) aria-current="page" @endif>All<span class="adm-count">{{ $counts->sum() }}</span></a>
        @foreach(\App\Http\Controllers\Backend\Admin\AlertsController::STATUSES as $s)
        <a href="{{ request()->fullUrlWithQuery(['status' => $s, 'page' => null]) }}" @if($status === $s) aria-current="page" @endif>{{ ucfirst($s) }}<span class="adm-count">{{ $counts[$s] ?? 0 }}</span></a>
        @endforeach
    </nav>
    <x-backend.filters :action="route('backend.admin.alerts.index')" :filters="$filters" :export="route('backend.admin.alerts.deliveries.export')" placeholder="Account email or error text" :total="$deliveries->total()" noun="delivery">
        @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <div><label for="f-kind" class="adm-label">Channel</label><select id="f-kind" name="kind" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option><option value="slack" @selected(request('kind') === 'slack')>Slack</option><option value="webhook" @selected(request('kind') === 'webhook')>Webhook</option></select></div>
    </x-backend.filters>

    @if($deliveries->isEmpty())<div class="mt-4"><x-site.empty :title="$status || $filters->active() || request('kind') ? 'No deliveries match these filters' : 'No Slack or webhook deliveries yet'" :reset="$status || $filters->active() || request('kind') ? route('backend.admin.alerts.index') : null">A delivery is queued for every enabled Slack or webhook channel when the daily alert goes out.</x-site.empty></div>@else
    @if($canManage)
    {{-- Posts to a URL carrying this list's query string, so "all matching" retries exactly the
         unsent deliveries the filters show, up to the per-action limit. --}}
    @php($retryable = $deliveries->getCollection()->where('status', '!=', 'sent')->count())
    <form method="post" action="{{ route('backend.admin.alerts.deliveries.retry.many', request()->except(['page'])) }}" id="bulk-deliveries" class="adm-bulkbar sticky top-0 z-10 mt-4 flex flex-wrap items-center gap-2 rounded-md border border-brand-line bg-white p-3 text-sm shadow-sm">@csrf
        <span class="font-medium text-brand-navy">Act on the selection</span>
        <span class="badge-neutral" data-bulk-count="bulk-deliveries" data-bulk-count-all="all {{ $unsentMatching }} unsent matching">0 selected</span>
        @if($unsentMatching > $retryable || $unsentMatching > $retryMax)<label class="flex items-center gap-1 meta"><input type="checkbox" name="scope" value="filtered" data-bulk-scope="bulk-deliveries" data-bulk-scope-count="{{ min($unsentMatching, $retryMax) }}"> apply to all {{ $unsentMatching }} unsent deliveries matching the filters{{ $unsentMatching > $retryMax ? ' (up to '.$retryMax.' per action)' : '' }}</label>@endif
        <span class="meta adm-bulkbar-hint">Up to {{ $retryMax }} at a time; sent ones are skipped.</span>
        <span class="ml-auto"><button type="submit" class="btn-secondary btn-sm" data-bulk-needs="bulk-deliveries" data-confirm="Try {n} deliveries again now? Each counts as an attempt.">Retry now</button></span>
    </form>
    @endif
    <div class="table-wrap mt-3"><table><caption class="sr-only">Slack and webhook deliveries</caption><thead><tr>
        @if($canManage)<th scope="col" class="w-8"><input type="checkbox" data-bulk-all="bulk-deliveries" aria-label="Select every delivery on this page"></th>@endif
        <x-backend.sort-th key="created" label="Queued" :filters="$filters" />
        <th scope="col">Account</th>
        <th scope="col">Channel</th>
        <x-backend.sort-th key="status" label="Status" :filters="$filters" />
        <x-backend.sort-th key="attempts" label="Attempts" :filters="$filters" />
        <th scope="col">Last error</th>
        <x-backend.sort-th key="next" label="Next attempt" :filters="$filters" />
        @if($canManage)<th scope="col">Actions</th>@endif
    </tr></thead>
    <tbody>@foreach($deliveries as $d)<tr>
        @if($canManage)<td>@if($d->status !== 'sent')<input type="checkbox" name="ids[]" value="{{ $d->id }}" form="bulk-deliveries" data-bulk-item aria-label="Select delivery {{ $d->id }}">@endif</td>@endif
        <td class="whitespace-nowrap text-xs">{{ $d->created_at?->format('j M Y H:i') }}<br><span class="font-mono text-brand-muted">#{{ $d->id }} · {{ $d->payload['event'] ?? 'alert' }}</span></td>
        <td class="text-xs">@php($u = $d->channel?->user)@if($u && $canUsers)<a href="{{ route('backend.admin.users.show', $u) }}">{{ $u->email }}</a>@else{{ $u?->email ?? '—' }}@endif</td>
        <td class="text-xs">@if($d->channel){{ $d->channel->kindLabel() }}@unless($d->channel->enabled) <x-backend.badge status="no">disabled</x-backend.badge>@endunless<br><span class="font-mono text-brand-muted">{{ $d->channel->endpointLabel() }}</span>@else — @endif</td>
        <td class="whitespace-nowrap"><x-backend.badge :status="match ($d->status) { 'sent' => 'ok', 'failed' => 'failed', default => 'running' }">{{ $d->status }}</x-backend.badge>@if($d->response_code) <span class="text-xs text-brand-muted">HTTP {{ $d->response_code }}</span>@endif</td>
        <td class="tabular-nums">{{ $d->attempts }} / {{ \App\Models\ChannelDelivery::MAX_ATTEMPTS }}</td>
        <td class="text-xs font-mono break-all {{ $d->last_error ? 'text-state-bad' : 'text-brand-muted' }}">{{ $d->last_error ?? '—' }}</td>
        <td class="whitespace-nowrap text-xs">{{ $d->status === 'pending' ? ($d->next_attempt_at?->format('j M Y H:i') ?? 'next run') : ($d->sent_at ? 'sent '.$d->sent_at->format('j M Y H:i') : '—') }}</td>
        @if($canManage)<td class="whitespace-nowrap">@if($d->status !== 'sent')<form method="post" action="{{ route('backend.admin.alerts.deliveries.retry', $d) }}" class="inline" data-confirm="Try delivery #{{ $d->id }} again now?">@csrf<button type="submit" class="btn-secondary btn-sm text-xs">Retry now</button></form>@else — @endif</td>@endif
    </tr>@endforeach</tbody></table></div>
    <nav class="mt-4" aria-label="Pagination">{{ $deliveries->links() }}</nav>
    @endif
</section>

<section class="mt-8" id="consent">
    <h2 class="section-title !text-lg">Consent events</h2>
    <p class="mt-1 meta">The latest 100 consent decisions, written when they are made. No IP address or browser is stored.</p>
    <form method="get" action="{{ route('backend.admin.alerts.index') }}#consent" class="adm-toolbar">
        @foreach(request()->except(['consent', 'page']) as $k => $v)@if(is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
        <div><label for="f-consent" class="adm-label">Kind</label><select id="f-consent" name="consent" class="input !min-h-[38px] !py-1.5 !w-auto"><option value="">Any</option>@foreach($consentKinds as $k)<option value="{{ $k }}" @selected($consentKind === $k)>{{ $k }}</option>@endforeach</select></div>
        <div class="flex items-end"><button type="submit" class="btn-primary btn-sm">Apply</button></div>
    </form>
    @if($consents->isEmpty())<div class="mt-4"><x-site.empty title="No consent events{{ $consentKind ? ' of this kind' : ' yet' }}">An event is written when an account turns its inbox alerts on or off, adds or removes a channel, or uses an unsubscribe link.</x-site.empty></div>@else
    <div class="table-wrap mt-3"><table><caption class="sr-only">Consent events</caption><thead><tr><th scope="col">When</th><th scope="col">Account</th><th scope="col">Kind</th><th scope="col">Decision</th><th scope="col">Source</th><th scope="col">Detail</th></tr></thead>
    <tbody>@foreach($consents as $c)<tr>
        <td class="whitespace-nowrap text-xs">{{ $c->created_at?->format('j M Y H:i') }}</td>
        <td class="text-xs">@if($c->user && $canUsers)<a href="{{ route('backend.admin.users.show', $c->user) }}">{{ $c->user->email }}</a>@else{{ $c->user?->email ?? '—' }}@endif</td>
        <td class="font-mono text-xs">{{ $c->kind }}</td>
        <td><x-backend.badge :status="$c->granted ? 'yes' : 'no'">{{ $c->granted ? 'granted' : 'withdrawn' }}</x-backend.badge></td>
        <td class="text-xs">{{ $c->source }}</td>
        <td class="text-xs text-brand-muted">{{ $c->detail ?? '—' }}</td>
    </tr>@endforeach</tbody></table></div>
    @endif
</section>
@endsection
