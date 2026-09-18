@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <div class="mt-3 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <p class="eyebrow">Weekly digest</p>
            <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Follow AI policy changes by email</h1>
            <p class="mt-3 max-w-[64ch] text-brand-body leading-7">One email a week with every dated, source-linked change in the jurisdictions you choose, plus application dates falling due in the next 60 days. Double opt-in, no marketing, one-click unsubscribe in every message.</p>

            <form method="post" action="{{ route('subscribe.store') }}" class="mt-8 card-flat p-4 sm:p-6 space-y-5" aria-label="Subscribe to the weekly digest">
                @csrf
                <input type="hidden" name="source" value="subscribe-page">
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                @if(session('success'))<p class="rounded-sm border border-state-good/30 bg-state-goodbg p-3 text-sm text-state-good" role="status">{{ session('success') }}</p>@endif
                @if($errors->any())<div class="rounded-sm border border-state-bad/30 bg-state-badbg p-3 text-sm text-state-bad" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
                <div>
                    <label for="sub-email" class="label">Email address <span class="text-state-bad" aria-hidden="true">*</span></label>
                    <input id="sub-email" type="email" name="email" required class="input max-w-md" placeholder="you@example.org" autocomplete="email" value="{{ old('email') }}">
                    <p class="mt-1 text-xs text-brand-muted">Work or personal, whichever you read. Temporary mailboxes are not accepted: an alert sent to one never reaches anybody.</p>
                </div>
                <fieldset>
                    <legend class="label">Jurisdictions to follow</legend>
                    <p class="text-xs text-brand-muted">Leave everything unticked to receive all jurisdictions.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($jurisdictions as $region => $items)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-muted">{{ $region ?: 'Other' }}</p>
                            <ul class="mt-1.5 space-y-1">
                                @foreach($items as $j)
                                <li><label class="flex items-center gap-2 text-sm text-brand-body"><input type="checkbox" name="topics[]" value="{{ $j->slug }}" class="rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy" @checked(in_array($j->slug, old('topics', $selected), true))>{{ $j->name }}</label></li>
                                @endforeach
                            </ul>
                        </div>
                        @endforeach
                    </div>
                </fieldset>
                <p class="text-xs text-brand-muted">We store your address, chosen topics and timestamps, nothing else. Confirmation and unsubscribe links are unique to you.</p>
                <button type="submit" class="btn-primary">Subscribe</button>
            </form>
        </div>
        <aside class="space-y-6">
            <div class="card-flat p-4 text-sm">
                <p class="font-semibold text-brand-navy">What you receive</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-brand-body">
                    <li>Changes recorded in the last seven days for your jurisdictions</li>
                    <li>Application dates and deadlines in the next 60 days</li>
                    <li>A link to the official source for every item</li>
                </ul>
                <p class="mt-3 meta">Sent Mondays, 06:00 UTC. @if($subscriberCount > 0){{ number_format($subscriberCount) }} confirmed subscriber{{ $subscriberCount === 1 ? '' : 's' }}.@endif</p>
            </div>
            @if($recent->isNotEmpty())
            <div class="text-sm">
                <p class="font-semibold text-brand-navy">Recent changes</p>
                <ul class="mt-2 space-y-2">@foreach($recent as $c)<li><a href="{{ route('changes.year', $c->occurred_on->year) }}#{{ $c->slug }}" class="text-brand-body hover:underline">{{ $c->title }}</a><div class="text-xs text-brand-muted">{{ $c->jurisdiction?->name }} · {{ $c->occurred_on->format('j M Y') }}</div></li>@endforeach</ul>
                <a href="{{ route('changes.feed') }}" class="mt-2 inline-block text-xs text-brand-muted hover:text-brand-navy">Prefer RSS? Use the feed</a>
            </div>
            @endif
            <div class="text-sm">
                <p class="font-semibold text-brand-navy">Follow a single policy</p>
                <p class="mt-1 text-brand-body">Every policy page has a "Follow" box that subscribes you to changes for that instrument only.</p>
            </div>
        </aside>
    </div>
</div>
@endsection
