@extends('backend.layouts.app', ['title' => 'Settings and API keys'])
@section('content')
{{-- One card and one form per group: a Save saves only that group's keys, so a mistake in
     one group never empties or blocks another. Live switches sit apart and ask first. --}}
@php
    $groupErrors = collect($groups)->map(fn ($g, $id) => collect($g['keys'])->contains(fn ($k) => $errors->has($k)) || ($id === 'email' && $errors->has('to')));
@endphp
<x-backend.page-header title="Settings and API keys" description="Values saved here override the environment. Secrets are encrypted with the application key before they are stored and are never shown again in full.">
    <x-slot:actions><a href="{{ route('backend.admin.billing.index') }}" class="btn-secondary btn-sm">Billing</a><a href="{{ route('backend.admin.funding.index') }}" class="btn-secondary btn-sm">Funding and funders</a></x-slot:actions>
</x-backend.page-header>
@unless($envReadable)<p class="mt-3 rounded-sm border border-brand-line bg-white px-3 py-2 text-sm text-brand-body">The configuration is cached on this host, so the environment file is not read at request time and its values cannot be shown beside each field. A field not saved here shows "Environment or default".</p>@endunless

<div class="mt-6 lg:grid lg:grid-cols-[13rem_minmax(0,1fr)] lg:items-start lg:gap-8">
    <nav aria-label="Settings groups" class="mb-6 lg:sticky lg:top-6 lg:mb-0" data-settings-nav>
        <p class="mb-2 hidden text-xs font-semibold uppercase tracking-wide text-brand-muted lg:block">On this page</p>
        <ul class="flex flex-wrap gap-2 text-sm lg:flex-col lg:gap-0.5">
            @foreach($groups as $id => $group)
            <li><a href="#group-{{ $id }}" data-command="Settings: {{ $group['label'] }}" class="inline-flex items-center gap-2 rounded-sm border border-brand-line bg-white px-2.5 py-1 text-brand-navy no-underline hover:bg-brand-paper lg:flex lg:border-0 lg:bg-transparent lg:px-2 lg:py-1.5 @if($id === 'live') text-state-bad @endif">{{ $group['label'] }}@if($groupErrors[$id])<span class="inline-block h-2 w-2 rounded-full bg-state-bad" aria-label="has errors"></span>@endif</a></li>
            @endforeach
        </ul>
    </nav>

    <div class="min-w-0 space-y-6">
        @foreach($groups as $id => $group)
        @continue($id === 'live')
        <section id="group-{{ $id }}" class="card-flat scroll-mt-6 p-5" aria-labelledby="group-{{ $id }}-title" data-settings-group="{{ $id }}">
            <h2 id="group-{{ $id }}-title" class="section-title !text-lg">{{ $group['label'] }}</h2>
            <p class="mt-1 text-sm text-brand-body">{{ $group['intro'] }}</p>
            @if($groupErrors[$id])<p class="mt-3 rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">Nothing in this group was saved. Fix the fields marked below and save again.</p>@endif

            @switch($id)
                @case('email')
                <p class="mt-2 text-sm text-brand-body">Create a key at <a href="https://resend.com/api-keys" rel="noopener">resend.com/api-keys</a> with "Sending access" for the verified domain, paste it below and set the transport to <code>resend</code>. Then send a test email.</p>
                <dl class="mt-3 divide-y divide-brand-line rounded-sm border border-brand-line bg-brand-paper px-3 text-sm" data-effective-mail>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-brand-muted">Mail transport in use</dt><dd class="font-mono">{{ $effective['mailer'] }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-brand-muted">From</dt><dd class="font-mono break-all text-right">{{ $effective['from'] }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-brand-muted">Resend key</dt><dd>{{ $effective['resend'] ? 'configured' : 'not configured' }}</dd></div>
                </dl>
                @break
                @case('digest')
                <p class="mt-2 text-sm text-brand-body">The "Weekly digest" and "Daily alerts" workflows send this token. Store the same value in the repository secret <code>CRON_TOKEN</code>.</p>
                @break
                @case('bot')
                <p class="mt-2 text-sm text-brand-body" id="turnstile">
                    Create a widget in the Cloudflare dashboard (Turnstile → Add widget, mode <strong>Managed</strong>, hostname <code class="font-mono text-xs">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>), then paste both keys here.
                    @switch($turnstile->source())
                        @case('settings')<span class="text-state-good">Active, using the keys saved here.</span>@break
                        @case('environment')<span class="text-state-good">Active, using TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY from the environment.</span>@break
                        @default<span class="text-state-warn">Not configured: the forms rely on the honeypot, the email checks and rate limits only.</span>
                    @endswitch
                </p>
                @break
                @case('social')
                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-brand-line pt-4" data-settings-test="social">
                    <a href="{{ route('backend.admin.social.index') }}" class="btn-secondary">Posts to X</a>
                    <span class="meta">Preview the next posts, approve drafts and see what was sent. Turning posting on is under Live switches below.</span>
                </div>
                @break
                @case('billing')
                <p class="mt-2 text-sm text-brand-body">Point the Dodo webhook at <code class="break-all">{{ route('billing.webhook') }}</code> with the subscription and payment events enabled. Easier: store the API key, then use "Provision webhook and products" on <a href="{{ route('backend.admin.billing.index') }}#setup">Billing</a> to create the endpoint and plans and fill the remaining fields. Checkout and the live environment are under <a href="#group-live">Live switches</a>.</p>
                @break
                @case('citation')
                <p class="mt-2 text-sm text-brand-body" id="citation-funding">The dataset DOI appears in the "Cite this record" box, on /open-data and in its structured data. Set it only after Zenodo has minted the concept DOI; the steps are in <code>docs/reference/releases.md</code> under "One-time setup (maintainer)". A value saved here replaces the <code>DATASET_DOI</code> step (step 3), so no redeploy is needed. The sponsor link and threshold are shown on <a href="{{ route('funding') }}">/funding</a>; the funders themselves are kept on <a href="{{ route('backend.admin.funding.index') }}">Funding and funders</a>.</p>
                <p class="meta mt-2" data-effective-citation>In use now: DOI {{ $citation['doi'] ?? 'none (nothing DOI-related is shown)' }} · sponsor link {{ $citation['sponsor'] ?? 'none' }} · threshold {{ '$'.number_format($citation['threshold']) }} a year (default in config/funding.php: {{ '$'.number_format((int) config('funding.disclosure_threshold')) }}).</p>
                @break
            @endswitch

            <form method="post" action="{{ route('backend.admin.settings.save') }}" class="mt-5 space-y-5" data-settings-form="{{ $id }}">@csrf
                <input type="hidden" name="group" value="{{ $id }}">
                @foreach($group['keys'] as $key)
                    @include('backend.admin.settings.field', ['key' => $key])
                @endforeach
                <div class="flex flex-wrap items-center gap-3 border-t border-brand-line pt-4">
                    <button type="submit" class="btn-primary">Save {{ \Illuminate\Support\Str::lower($group['label']) }}</button>
                    <span class="meta">Saves only this group.</span>
                </div>
            </form>

            @switch($id)
                @case('email')
                <form method="post" action="{{ route('backend.admin.settings.test') }}" class="mt-5 flex flex-wrap items-end gap-3 border-t border-brand-line pt-4" data-settings-test="email">@csrf
                    <div class="min-w-[240px] flex-1">
                        <label for="test-to" class="label">Send a test email to</label>
                        <input id="test-to" name="to" type="email" class="input" value="{{ old('to', auth()->user()->email) }}" @error('to') aria-invalid="true" aria-describedby="test-to-error" @enderror>
                        @error('to')<p id="test-to-error" class="mt-1 text-xs font-medium text-state-bad">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-secondary">Send test email</button>
                    <p class="meta w-full">Sends through the transport in use now ({{ $effective['mailer'] }}). Save first if you changed it.</p>
                </form>
                @break
                @case('bot')
                <div class="mt-5 border-t border-brand-line pt-4" data-settings-test="turnstile">
                    @if($turnstile->configured())
                    <form method="post" action="{{ route('backend.admin.settings.turnstile') }}" class="flex flex-wrap items-center gap-3">@csrf<button type="submit" class="btn-secondary">Check keys with Cloudflare</button><span class="meta">Asks Cloudflare whether the secret key is valid. Nothing is shown to visitors.</span></form>
                    @else
                    <p class="meta">"Check keys with Cloudflare" appears once both keys are saved here or set in the environment.</p>
                    @endif
                </div>
                @break
                @case('billing')
                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-brand-line pt-4" data-settings-test="billing">
                    <a href="{{ route('backend.admin.billing.index') }}#setup" class="btn-secondary">Can we sell right now?</a>
                    <span class="meta">Opens Billing → Setup, where the probe asks Dodo to open a checkout session. Nothing is charged.</span>
                </div>
                @break
            @endswitch
        </section>
        @endforeach

        {{-- Live switches: each changes what visitors or customers meet on the next request.
             Moving one towards its live value asks first and names the consequence. --}}
        @php
            $liveGroup = $groups['live'];
        @endphp
        <section id="group-live" class="scroll-mt-6 rounded-md border-2 border-state-bad/40 bg-white p-5" aria-labelledby="group-live-title" data-settings-group="live">
            <h2 id="group-live-title" class="section-title !text-lg text-state-bad">{{ $liveGroup['label'] }}</h2>
            <p class="mt-1 text-sm text-brand-body">{{ $liveGroup['intro'] }}</p>
            @if($groupErrors['live'])<p class="mt-3 rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">That switch was not changed. {{ $errors->first() }}</p>@endif
            <div class="mt-4 divide-y divide-brand-line">
                @foreach($liveGroup['keys'] as $key)
                @php
                    $v = $values[$key];
                    $meta = $v['meta'];
                    $now = $live[$key];
                    $fallback = $v['env'];
                @endphp
                <div class="grid gap-3 py-4 sm:grid-cols-12" data-live-switch="{{ $key }}">
                    <div class="sm:col-span-7">
                        <p class="font-medium text-brand-navy">{{ $meta['label'] }} <span class="ml-1 font-mono text-sm {{ isset($meta['live'][$now]) ? 'text-state-bad' : 'text-brand-body' }}" data-live-now>{{ $now }}</span></p>
                        @include('backend.admin.settings.source', ['v' => $v])
                        <p class="meta mt-1">{{ $meta['hint'] }}@if($fallback) · environment: {{ $fallback }}@endif</p>
                        @error($key)<p class="mt-1 text-xs font-medium text-state-bad">{{ $message }}</p>@enderror
                    </div>
                    <form method="post" action="{{ route('backend.admin.settings.save') }}" class="flex flex-wrap items-start gap-2 sm:col-span-5 sm:justify-end">@csrf
                        <input type="hidden" name="group" value="live">
                        @foreach($meta['options'] as $opt => $optLabel)
                            @continue((string) $opt === (string) $now)
                            @php
                                $verb = match ((string) $opt) { 'on' => 'Turn on', 'off' => 'Turn off', default => 'Switch to '.$opt };
                            @endphp
                            @if(isset($meta['live'][$opt]))
                            <button type="submit" name="{{ $key }}" value="{{ $opt }}" class="btn-danger" data-confirm="{{ $verb }}: {{ $meta['label'] }}? {{ $meta['live'][$opt] }}" data-confirm-danger data-confirm-label="Yes, {{ \Illuminate\Support\Str::lower($verb) }}">{{ $verb }}</button>
                            @else
                            <button type="submit" name="{{ $key }}" value="{{ $opt }}" class="btn-secondary">{{ $verb }}</button>
                            @endif
                        @endforeach
                        @if($v['set'])
                            @if($fallback !== null && isset($meta['live'][$fallback]) && $fallback !== $now)
                            <button type="submit" name="clear[]" value="{{ $key }}" class="btn-secondary" data-confirm="Use the environment value for {{ $meta['label'] }} ({{ $fallback }})? {{ $meta['live'][$fallback] }}" data-confirm-danger data-confirm-label="Yes, use the environment">Use the environment</button>
                            @else
                            <button type="submit" name="clear[]" value="{{ $key }}" class="btn-secondary">Use the {{ $fallback !== null ? 'environment' : 'default' }}</button>
                            @endif
                        @endif
                    </form>
                </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection
