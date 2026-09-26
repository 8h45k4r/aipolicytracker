@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-3xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Daily alerts</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Records you follow</h1>
    <p class="mt-3 text-brand-body leading-7">Every morning we check the policies, jurisdictions and obligations below. When a dated, source-linked change is recorded, or an application date is 30, 7 or 1 days away, you get one email at {{ $user->email }}. Quiet days send nothing.</p>
    @if(session('status') === 'profile-saved')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Profile saved. The daily alert will name it when a change may affect it.</p>@endif
    @if(session('status') === 'profile-exists')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">You already have a profile with those answers.</p>@endif
    @if(session('status') === 'profile-deleted')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Profile deleted.</p>@endif
    @if(session('status') === 'followed')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Watching.</p>@endif
    @if(session('status') === 'unfollowed')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Unfollowed.</p>@endif
    <p class="mt-3 meta">@if($lastAlert)Last alert sent {{ $lastAlert->sent_on->format('j M Y') }} ({{ $lastAlert->changes_count }} {{ \Illuminate\Support\Str::plural('change', $lastAlert->changes_count) }}).@else No alert sent yet.@endif</p>
    @if($follows->isEmpty())
    <div class="mt-6"><x-site.empty title="You are not following anything yet">Open any policy, jurisdiction or obligation and use "Follow for daily alerts" in its sidebar.</x-site.empty>
    <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('policies.index') }}" class="btn-primary">Browse policies</a><a href="{{ route('jurisdictions.index') }}" class="btn-secondary">Browse jurisdictions</a></div></div>
    @else
    <ul class="mt-6 divide-y divide-brand-line border-y border-brand-line" aria-label="Followed records">
        @foreach($follows as $row)
        <li class="flex flex-wrap items-start justify-between gap-3 py-3">
            <div>@if($row['url'])<a href="{{ $row['url'] }}" class="font-medium text-brand-navy hover:underline">{{ $row['title'] }}</a>@else<span class="font-medium text-brand-navy">{{ $row['title'] }}</span> <span class="meta">(no longer published)</span>@endif
                <div class="text-xs text-brand-muted">{{ \App\Models\Follow::typeLabel($row['follow']->subject_type) }} · following since {{ $row['follow']->created_at->format('j M Y') }}</div></div>
            <form method="post" action="{{ route('follow.toggle', [$row['follow']->subject_type, $row['follow']->subject_slug]) }}">@csrf<input type="hidden" name="return" value="{{ route('following.index', absolute: false) }}"><button type="submit" class="btn-secondary !min-h-[36px] !py-1">Unfollow</button></form>
        </li>
        @endforeach
    </ul>
    @endif
    <section class="mt-10" aria-labelledby="watch-h">
        <h2 id="watch-h" class="section-title">Watch more than a record</h2>
        <p class="mt-1 text-sm text-brand-body">A watch on a sector, use case or framework covers every instrument recorded against it; a change-type watch covers every change at that level; a saved search covers the updates hub filtered your way.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach(['sector' => ['Sector', $options['sectors']], 'use_case' => ['Use case', $options['use_cases']], 'framework' => ['Framework', $options['frameworks']], 'change_type' => ['Change type', $options['change_types']]] as $type => [$label, $choices])
            <form method="post" action="{{ route('follow.toggle', [$type, '-']) }}" class="flex items-end gap-2">@csrf<input type="hidden" name="return" value="{{ route('following.index', absolute: false) }}">
                <div class="flex-1"><label for="w-{{ $type }}" class="label">{{ $label }}</label><select id="w-{{ $type }}" name="slug" class="input" required><option value="">Choose…</option>@foreach($choices as $slug => $name)<option value="{{ $slug }}">{{ $name }}</option>@endforeach</select></div>
                <button type="submit" class="btn-secondary !min-h-[42px]">Watch</button>
            </form>
            @endforeach
            <form method="post" action="{{ route('follow.toggle', ['search', 'updates']) }}" class="sm:col-span-2 grid gap-2 sm:grid-cols-4 items-end">@csrf<input type="hidden" name="return" value="{{ route('following.index', absolute: false) }}">
                <div><label for="w-sj" class="label">Saved search: jurisdiction</label><select id="w-sj" name="params[jurisdiction]" class="input"><option value="">Any</option>@foreach($options['jurisdictions'] as $slug => $name)<option value="{{ $slug }}">{{ $name }}</option>@endforeach</select></div>
                <div><label for="w-si" class="label">Impact</label><select id="w-si" name="params[impact]" class="input"><option value="">Any</option>@foreach($options['change_types'] as $slug => $name)<option value="{{ $slug }}">{{ $name }}</option>@endforeach</select></div>
                <div><label for="w-sq" class="label">Keyword</label><input id="w-sq" name="params[q]" class="input" maxlength="80" placeholder="e.g. biometric"></div>
                <button type="submit" class="btn-secondary !min-h-[42px]">Watch this search</button>
            </form>
        </div>
    </section>

    <section class="mt-10" aria-labelledby="chan-h">
        <h2 id="chan-h" class="section-title">Where alerts go</h2>
        @if(session('status') === 'channel-added')<p class="mt-3 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Channel added.@if(session('channel_secret')) Signing secret (shown once; verify <code>X-AIP-Signature: sha256=HMAC-SHA256(body)</code> with it): <code class="break-all">{{ session('channel_secret') }}</code>@endif</p>@endif
        @if(session('status') === 'channel-test-ok')<p class="mt-3 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Test delivered.</p>@endif
        @if(session('status') === 'channel-test-failed')<p class="mt-3 rounded-sm border border-state-bad/30 bg-red-50 px-3 py-2 text-sm text-state-bad" role="alert">Test failed: {{ session('channel_error') ?: 'no response' }}. It will be retried up to five times with backoff.</p>@endif
        <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm" aria-label="Alert channels">
            <li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><span class="font-medium text-brand-navy">Email</span> <span class="meta">{{ $user->email }} · {{ $emailOn ? 'on' : 'off' }}</span></div>
                @php($emailChannel = $channels->firstWhere('kind', 'email'))
                @if($emailChannel)<form method="post" action="{{ route('alerts.channels.toggle', $emailChannel) }}">@csrf<button type="submit" class="btn-secondary !min-h-[36px] !py-1">{{ $emailChannel->enabled ? 'Turn off' : 'Turn on' }}</button></form>@else<form method="post" action="{{ route('alerts.channels.email-off') }}">@csrf<button type="submit" class="btn-secondary !min-h-[36px] !py-1">Turn off</button></form>@endif</li>
            @foreach($channels->where('kind', '!=', 'email') as $c)
            <li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><span class="font-medium text-brand-navy">{{ $c->kindLabel() }}</span> <span class="meta">{{ $c->endpointLabel() }} · {{ $c->enabled ? 'on' : 'off' }}@if($c->last_delivered_at) · last delivered {{ $c->last_delivered_at->format('j M Y') }}@endif</span>
                @if($c->feedUrl())<p class="mt-1 text-xs break-all"><code>{{ $c->feedUrl() }}</code> <span class="meta">private: the address is the key</span></p>@endif
                @php($failed = $c->deliveries()->where('status', 'failed')->count())@if($failed)<p class="mt-1 text-xs text-state-bad">{{ $failed }} {{ \Illuminate\Support\Str::plural('delivery', $failed) }} failed after five attempts.</p>@endif</div>
                <div class="flex gap-2">@if(in_array($c->kind, ['slack', 'webhook'], true))<form method="post" action="{{ route('alerts.channels.test', $c) }}">@csrf<button type="submit" class="btn-secondary !min-h-[36px] !py-1">Send test</button></form>@endif
                <form method="post" action="{{ route('alerts.channels.toggle', $c) }}">@csrf<button type="submit" class="btn-secondary !min-h-[36px] !py-1">{{ $c->enabled ? 'Pause' : 'Resume' }}</button></form>
                <form method="post" action="{{ route('alerts.channels.destroy', $c) }}">@csrf @method('DELETE')<button type="submit" class="btn-secondary !min-h-[36px] !py-1">Remove</button></form></div></li>
            @endforeach
        </ul>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <form method="post" action="{{ route('alerts.channels.store') }}">@csrf<input type="hidden" name="kind" value="rss"><button type="submit" class="btn-secondary w-full" @disabled($channels->contains('kind', 'rss'))>Add private RSS feed</button></form>
            <form method="post" action="{{ route('alerts.channels.store') }}" class="flex gap-2">@csrf<input type="hidden" name="kind" value="slack"><input name="endpoint" type="url" class="input" placeholder="https://hooks.slack.com/services/…" required aria-label="Slack incoming webhook URL"><button type="submit" class="btn-secondary">Add Slack</button></form>
            <form method="post" action="{{ route('alerts.channels.store') }}" class="flex gap-2">@csrf<input type="hidden" name="kind" value="webhook"><input name="endpoint" type="url" class="input" placeholder="https://your-host/webhook" required aria-label="Webhook URL"><button type="submit" class="btn-secondary">Add webhook</button></form>
        </div>
        @error('endpoint')<p class="mt-2 text-xs text-state-bad" role="alert">{{ $message }}</p>@enderror
        <p class="mt-2 text-xs text-brand-muted">Webhooks receive JSON with <code>X-AIP-Signature</code> (HMAC-SHA256 of the body), <code>X-AIP-Delivery</code> and <code>X-AIP-Event</code>; failures retry with backoff, five attempts, and the log is kept here. Every consent change is recorded; <a href="{{ route('account.export') }}">export everything we hold about you</a>.</p>
    </section>

    <section class="mt-10" aria-labelledby="prof-h">
        <h2 id="prof-h" class="section-title">Systems you screened</h2>
        <p class="mt-1 text-sm text-brand-body">Saved answers from the <a href="{{ route('tools.applicability') }}">applicability check</a>. A change inside a profile's scope is flagged in the daily alert with the profile's name, so you know which system to review.</p>
        @if($profiles->isEmpty())
        <p class="mt-4 text-sm text-brand-muted">No profiles yet. Run the <a href="{{ route('tools.applicability') }}">applicability check</a> and choose "Save and alert me".</p>
        @else
        <ul class="mt-4 divide-y divide-brand-line border-y border-brand-line" aria-label="Saved profiles">
            @foreach($profiles as $p)
            <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                <div><a href="{{ $p->url() }}" class="font-medium text-brand-navy hover:underline">{{ $p->name }}</a>
                    <div class="text-xs text-brand-muted">{{ $p->summary() }} · saved {{ $p->created_at->format('j M Y') }}@if($p->last_matched_at) · last flagged {{ $p->last_matched_at->format('j M Y') }}@endif</div></div>
                <form method="post" action="{{ route('profiles.destroy', $p) }}">@csrf @method('DELETE')<button type="submit" class="btn-secondary !min-h-[36px] !py-1">Delete</button></form>
            </li>
            @endforeach
        </ul>
        @endif
    </section>
    <p class="mt-6 text-sm"><a href="{{ route('profile.edit') }}">Back to your account</a></p>
</div>
@endsection
