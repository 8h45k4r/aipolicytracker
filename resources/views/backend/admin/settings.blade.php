@extends('backend.layouts.app', ['title' => 'Settings and API keys'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Settings and API keys</h1>
<p class="mt-1 meta">Values saved here override the environment. Secrets are encrypted with the application key before they are stored and are never shown again in full.</p>
<section class="mt-6 card-flat p-5"><h2 class="section-title !text-lg">Effective configuration</h2>
    <dl class="mt-3 text-sm divide-y divide-brand-line">
        <div class="py-2 flex justify-between"><dt class="text-brand-muted">Mail transport</dt><dd class="font-mono">{{ $effective['mailer'] }}</dd></div>
        <div class="py-2 flex justify-between"><dt class="text-brand-muted">From</dt><dd class="font-mono">{{ $effective['from'] }}</dd></div>
        <div class="py-2 flex justify-between"><dt class="text-brand-muted">Resend key</dt><dd>{{ $effective['resend'] ? 'configured' : 'not configured' }}</dd></div>
    </dl>
</section>
<form method="post" action="{{ route('backend.admin.settings.save') }}" class="mt-6 card-flat p-5 space-y-5">@csrf
    <h2 class="section-title !text-lg">Email delivery (Resend)</h2>
    <p class="text-sm text-brand-body">Create a key at <a href="https://resend.com/api-keys" rel="noopener">resend.com/api-keys</a> with "Sending access" for the verified domain, paste it below and set the transport to <code>resend</code>. Use "Send test" afterwards.</p>
    @foreach(['mail_mailer', 'resend_key', 'mail_from_address', 'mail_from_name'] as $key)
    @php($v = $values[$key])
    <div class="grid gap-1 sm:grid-cols-12 sm:gap-4 items-start">
        <label for="f-{{ $key }}" class="label sm:col-span-3 sm:pt-2">{{ $v['meta']['label'] }}</label>
        <div class="sm:col-span-9">
            @if($key === 'mail_mailer')
            <select id="f-{{ $key }}" name="{{ $key }}" class="input">@foreach(['' => 'Keep current', 'log' => 'log (no delivery)', 'resend' => 'resend', 'smtp' => 'smtp'] as $opt => $label)<option value="{{ $opt }}" @selected($opt !== '' && $v['display'] === $opt)>{{ $label }}</option>@endforeach</select>
            @else
            <input id="f-{{ $key }}" name="{{ $key }}" type="{{ $v['meta']['secret'] ? 'password' : 'text' }}" class="input" autocomplete="off" placeholder="{{ $v['meta']['secret'] ? ($v['set'] ? 'Stored: '.$v['display'] : 'Not set') : $v['display'] }}">
            @endif
            <p class="meta mt-1">{{ $v['meta']['hint'] }}@if($v['env']) · environment: {{ $v['env'] }}@endif @if($v['set'])<label class="ml-2"><input type="checkbox" name="clear[]" value="{{ $key }}"> clear stored value</label>@endif</p>
        </div>
    </div>
    @endforeach
    <h2 class="section-title !text-lg pt-2">Scheduled digest</h2>
    @php($v = $values['cron_token'])
    <div class="grid gap-1 sm:grid-cols-12 sm:gap-4 items-start">
        <label for="f-cron_token" class="label sm:col-span-3 sm:pt-2">{{ $v['meta']['label'] }}</label>
        <div class="sm:col-span-9"><input id="f-cron_token" name="cron_token" type="password" class="input" autocomplete="off" placeholder="{{ $v['set'] ? 'Stored: '.$v['display'] : 'Not set' }}"><p class="meta mt-1">{{ $v['meta']['hint'] }}; the same value goes into the repository secret <code>CRON_TOKEN</code> used by the "Weekly digest" workflow. Minimum 24 characters.@if($v['env']) · environment: {{ $v['env'] }}@endif @if($v['set'])<label class="ml-2"><input type="checkbox" name="clear[]" value="cron_token"> clear stored value</label>@endif</p></div>
    </div>
    <h2 class="section-title !text-lg pt-2">Billing (Dodo Payments)</h2>
    <p class="text-sm text-brand-body">Keys from the Dodo dashboard (Developer → API keys, Webhooks). Point the webhook at <code>{{ route('billing.webhook') }}</code> with the subscription and payment events enabled. Store the API key, then use "Provision webhook and products" on <a href="{{ route('backend.admin.billing.index') }}">Billing</a> to create the endpoint and plans and fill the remaining fields automatically. Checkout stays off until the switch below (or <code>BILLING_ENABLED</code>) is on.</p>
    @foreach(['billing_enabled', 'dodo_environment', 'dodo_api_key', 'dodo_webhook_secret', 'dodo_product_pro_monthly', 'dodo_product_pro_yearly'] as $key)
    @php($v = $values[$key])
    <div class="grid gap-1 sm:grid-cols-12 sm:gap-4 items-start">
        <label for="f-{{ $key }}" class="label sm:col-span-3 sm:pt-2">{{ $v['meta']['label'] }}</label>
        <div class="sm:col-span-9">
            @if($key === 'dodo_environment')
            <select id="f-{{ $key }}" name="{{ $key }}" class="input">@foreach(['' => 'Keep current', 'test_mode' => 'test_mode (sandbox)', 'live_mode' => 'live_mode'] as $opt => $label)<option value="{{ $opt }}" @selected($opt !== '' && $v['display'] === $opt)>{{ $label }}</option>@endforeach</select>
            @elseif($key === 'billing_enabled')
            <select id="f-{{ $key }}" name="{{ $key }}" class="input">@foreach(['' => 'Keep current', 'on' => 'on (sell plans)', 'off' => 'off (show plans as not yet available)'] as $opt => $label)<option value="{{ $opt }}" @selected($opt !== '' && $v['display'] === $opt)>{{ $label }}</option>@endforeach</select>
            @else
            <input id="f-{{ $key }}" name="{{ $key }}" type="{{ $v['meta']['secret'] ? 'password' : 'text' }}" class="input" autocomplete="off" placeholder="{{ $v['meta']['secret'] ? ($v['set'] ? 'Stored: '.$v['display'] : 'Not set') : ($v['display'] ?: 'Not set') }}">
            @endif
            <p class="meta mt-1">{{ $v['meta']['hint'] }}@if($v['env']) · environment: {{ $v['env'] }}@endif @if($v['set'])<label class="ml-2"><input type="checkbox" name="clear[]" value="{{ $key }}"> clear stored value</label>@endif</p>
        </div>
    </div>
    @endforeach
    <h2 class="section-title !text-lg pt-2">Site and features</h2>
    <p class="text-sm text-brand-body">Each value overrides the environment variable of the same meaning; an empty field keeps the environment value. Switches take effect on the next request.</p>
    @foreach(['contact_email', 'x_handle', 'newsletter_url', 'google_analytics_id', 'cloudflare_analytics_token', 'analytics_require_consent', 'social_cards_enabled', 'email_domain_enforcement', 'stale_after_days', 'google_site_verification', 'bing_site_verification'] as $key)
    @php($v = $values[$key])
    <div class="grid gap-1 sm:grid-cols-12 sm:gap-4 items-start">
        <label for="f-{{ $key }}" class="label sm:col-span-3 sm:pt-2">{{ $v['meta']['label'] }}</label>
        <div class="sm:col-span-9">
            @if(in_array($key, ['analytics_require_consent', 'social_cards_enabled', 'email_domain_enforcement'], true))
            <select id="f-{{ $key }}" name="{{ $key }}" class="input">@foreach(['' => 'Keep current', 'on' => 'on', 'off' => 'off'] as $opt => $label)<option value="{{ $opt }}" @selected($opt !== '' && $v['display'] === $opt)>{{ $label }}</option>@endforeach</select>
            @else
            <input id="f-{{ $key }}" name="{{ $key }}" type="{{ $v['meta']['secret'] ? 'password' : ($key === 'stale_after_days' ? 'number' : 'text') }}" class="input" autocomplete="off" placeholder="{{ $v['meta']['secret'] ? ($v['set'] ? 'Stored: '.$v['display'] : 'Not set') : ($v['display'] ?: 'Not set') }}">
            @endif
            <p class="meta mt-1">{{ $v['meta']['hint'] }}@if($v['env']) · environment: {{ $v['env'] }}@endif @if($v['set'])<label class="ml-2"><input type="checkbox" name="clear[]" value="{{ $key }}"> clear stored value</label>@endif</p>
        </div>
    </div>
    @endforeach
    <div class="flex gap-2"><button type="submit" class="btn-primary">Save settings</button></div>
</form>
<form method="post" action="{{ route('backend.admin.settings.test') }}" class="mt-6 card-flat p-5 flex flex-wrap items-end gap-3">@csrf
    <div class="flex-1 min-w-[240px]"><label for="test-to" class="label">Send a test message to</label><input id="test-to" name="to" type="email" class="input" value="{{ auth()->user()->email }}"></div>
    <button type="submit" class="btn-secondary">Send test</button>
</form>
@endsection
