@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-3xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Your account</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">{{ $user->name }}</h1>
    <p class="mt-1 text-sm text-brand-muted">{{ $user->email }} · member since {{ $user->created_at?->format('j M Y') }}@if($user->isAdmin()) · <a href="{{ route('backend.admin.dashboard') }}">Admin</a>@endif</p>
    @if(session('error'))<p class="mt-4 rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">{{ session('error') }}</p>@endif
    @if(session('status') === 'already-subscribed')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">You already have an active plan.</p>@endif
    @if(session('status') === 'profile-updated')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Profile saved.</p>@elseif(session('status') === 'password-updated')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Password updated.</p>@elseif(session('status') === 'verification-link-sent')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Verification link sent.</p>@endif
    <section class="mt-8 card-flat p-5" aria-labelledby="plan-h">
        <h2 id="plan-h" class="section-title !text-lg">Your plan</h2>
        @if($subscription)
        <p class="mt-2 text-sm text-brand-body"><strong>{{ $subscription->planName() }}</strong>
            @if($subscription->status === 'active') · renews {{ $subscription->current_period_end?->format('j M Y') ?? 'automatically' }}
            @elseif($subscription->status === 'cancelled') · ends {{ $subscription->current_period_end?->format('j M Y') }}
            @elseif(in_array($subscription->status, ['on_hold', 'past_due'])) · <span class="text-state-bad">payment failed, update your card to keep access</span>
            @endif</p>
        @if($subscription->revoked_at)<p class="mt-2 text-sm text-state-bad">Pro access ended on {{ $subscription->revoked_at->format('j M Y') }} because the payment was {{ $subscription->revoked_reason === 'chargeback' ? 'reversed by your bank' : 'refunded' }}.</p>@endif
        @if($billingEnabled)<form method="post" action="{{ route('billing.portal') }}" class="mt-3">@csrf<button type="submit" class="btn-secondary">Manage billing</button></form>@endif
        <p class="mt-2 meta">Invoices, payment method and cancellation are handled by our payment provider. Cancelling keeps Pro until the end of the paid period.</p>
        <p class="mt-3 text-sm"><a href="{{ route('following.index') }}">Records you follow ({{ $user->follows()->count() }})</a> · daily alerts go to {{ $user->email }} when something changes.</p>
        @else
        <p class="mt-2 text-sm text-brand-body"><strong>Free</strong> · every record, weekly digest, applicability check and open data@if($billingEnabled), and up to {{ $user->watchLimit() }} watches with a daily alert@endif.</p>
        <p class="mt-3 text-sm"><a href="{{ route('following.index') }}">What you watch ({{ $user->follows()->count() }}@if($billingEnabled) of {{ $user->watchLimit() }}@endif)</a></p>
        @if($billingEnabled)
        <p class="mt-2 text-sm text-brand-body">Pro adds up to 500 watches, alerts to Slack, a webhook or RSS, and saved applicability profiles that name the system a change may affect.</p>
        <p class="mt-3 text-sm"><a href="{{ route('pricing') }}" class="btn-secondary" data-track="profile_pricing_click">See Pro plans</a></p>
        @endif
        @endif
    </section>
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <section class="card-flat p-5" aria-labelledby="dl-h">
            <h2 id="dl-h" class="section-title !text-lg">Your downloads</h2>
            @if($downloads->isEmpty())<p class="mt-2 text-sm text-brand-muted">No downloads yet. <a href="{{ route('guides.index', ['access' => 'download']) }}">Browse the free tools</a>.</p>@else
            <ul class="mt-3 divide-y divide-brand-line text-sm">@foreach($downloads as $d)<li class="py-2 flex flex-wrap justify-between gap-2"><span>@if($d->tool)<a href="{{ $d->tool->url() }}">{{ $d->tool->title }}</a>@else{{ $d->resource_slug }}@endif <span class="meta">v{{ $d->version }}</span></span><span class="meta">{{ $d->created_at->format('j M Y') }} · @if($d->tool && $d->tool->status === 'published')<a href="{{ route('tools.ready', [$d->resource_slug, $d]) }}">get links</a>@else unavailable @endif</span></li>@endforeach</ul>@endif
        </section>
        <section class="card-flat p-5" aria-labelledby="pref-h">
            <h2 id="pref-h" class="section-title !text-lg">Updates and consent</h2>
            <dl class="mt-3 text-sm space-y-1.5 text-brand-body">
                <div class="flex justify-between gap-2"><dt class="text-brand-muted">Email verified</dt><dd>{{ $user->email_verified_at ? $user->email_verified_at->format('j M Y') : '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-brand-muted">Terms accepted</dt><dd>{{ $user->terms_accepted_at?->format('j M Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-brand-muted">Weekly digest</dt><dd>@if($digestActive)receiving · <a href="{{ route('subscribe.show') }}">change topics</a>@elseif($user->marketing_consent_at && !$user->email_verified_at)starts once you verify your address@else not receiving @endif</dd></div>
            </dl>
            @if(!$user->email_verified_at)<form method="post" action="{{ route('verification.send') }}" class="mt-3">@csrf<button type="submit" class="btn-secondary">Resend verification email</button></form>@endif
            <p class="mt-3 meta">Weekly digest subscriptions are managed from the <a href="{{ route('subscribe.show') }}">subscribe page</a> and the unsubscribe link in every email. Daily alert channels are on the <a href="{{ route('following.index') }}">alerts page</a>.</p>
            <p class="mt-2 meta"><a href="{{ route('account.export') }}">Export everything we hold about you (JSON)</a> · deleting the account below removes it all.</p>
            @if(session('api_token'))<p class="mt-3 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-xs text-state-good" role="status">API token (shown once): <code class="break-all">{{ session('api_token') }}</code></p>@endif
            <form method="post" action="{{ route('profile.api-token') }}" class="mt-3">@csrf<button type="submit" class="btn-secondary">{{ $user->tokens()->exists() ? 'Replace API token' : 'Create API token' }}</button> <span class="meta">for <code>/api/v1/watches</code></span></form>
        </section>
    </div>
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <form method="post" action="{{ route('profile.update') }}" class="card-flat p-5 space-y-3" aria-labelledby="prof-h">@csrf @method('PATCH')
            <h2 id="prof-h" class="section-title !text-lg">Profile</h2>
            <div><label for="name" class="label">Name</label><input id="name" name="name" class="input" required value="{{ old('name', $user->name) }}">@error('name')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
            <div><label for="email" class="label">Email address</label><input id="email" type="email" name="email" class="input" required value="{{ old('email', $user->email) }}">@error('email')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror<p class="mt-1 meta">Changing the address requires a new verification.</p></div>
            <div><label for="organization_name" class="label">Organisation <span class="meta font-normal">(optional)</span></label><input id="organization_name" name="organization_name" class="input" value="{{ old('organization_name', $user->organization_name) }}"></div>
            <label class="flex items-start gap-2 text-sm text-brand-body"><input type="checkbox" name="marketing_consent" value="1" @checked($digestActive || ($user->marketing_consent_at && !$user->email_verified_at)) class="mt-1 rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy"><span>Send me the weekly digest of AI policy changes, with official sources. Unsubscribe from any issue.</span></label>
            <button type="submit" class="btn-primary">Save profile</button>
        </form>
        <form method="post" action="{{ route('password.update') }}" class="card-flat p-5 space-y-3" aria-labelledby="pw-h">@csrf @method('PUT')
            <h2 id="pw-h" class="section-title !text-lg">Password</h2>
            <div><label for="current_password" class="label">Current password</label><input id="current_password" type="password" name="current_password" class="input" required autocomplete="current-password">@error('current_password', 'updatePassword')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="label">New password</label><input id="password" type="password" name="password" class="input" required autocomplete="new-password">@error('password', 'updatePassword')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation" class="label">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password"></div>
            <button type="submit" class="btn-primary">Update password</button>
        </form>
    </div>
    <details class="mt-6 card-flat p-5">
        <summary class="cursor-pointer text-sm font-semibold text-state-bad">Delete account</summary>
        <p class="mt-2 text-sm text-brand-body">Deleting your account removes your profile and download history permanently. Digest subscriptions are separate; use the unsubscribe link in any email.</p>
        <form method="post" action="{{ route('profile.destroy') }}" class="mt-3 flex flex-wrap gap-2 items-end" onsubmit="return confirm('Delete your account permanently?');">@csrf @method('DELETE')
            <div><label for="del-password" class="label">Confirm with your password</label><input id="del-password" type="password" name="password" class="input" required autocomplete="current-password">@error('password', 'userDeletion')<p class="mt-1 text-xs text-state-bad">{{ $message }}</p>@enderror</div>
            <button type="submit" class="btn-secondary !border-state-bad/40 !text-state-bad">Delete my account</button>
        </form>
    </details>
    <p class="mt-6 text-sm"><form method="post" action="{{ route('logout') }}" class="inline">@csrf<button type="submit" class="btn-secondary">Sign out</button></form></p>
</div>
@endsection
