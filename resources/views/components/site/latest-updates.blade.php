@props(['changes', 'jurisdiction' => null, 'title' => 'Latest updates', 'limit' => 5])
{{--
The "latest updates" module: the same records the updates hub is built from,
in compact form, with the way to the hub, the jurisdiction's own page and its
feed. One component, so home, policy and jurisdiction pages agree.
--}}
@php($items = collect($changes)->take($limit))
<section {{ $attributes->merge(['class' => '']) }} aria-labelledby="latest-updates-heading">
    <div class="flex items-baseline justify-between rule-strong pt-3">
        <h2 id="latest-updates-heading" class="section-title">{{ $title }}</h2>
        <a href="{{ $jurisdiction ? route('updates.jurisdiction', $jurisdiction->slug) : route('updates.index') }}" class="text-sm">All updates{{ $jurisdiction ? ' for '.($jurisdiction->short_name ?: $jurisdiction->name) : '' }}</a>
    </div>
    @if($items->isNotEmpty())
        @foreach($items as $change)<x-site.change-item :change="$change" compact />@endforeach
        <p class="mt-3 text-xs text-brand-muted"><a href="{{ $jurisdiction ? route('updates.jurisdiction.feed', $jurisdiction->slug) : route('changes.feed') }}" class="hover:text-brand-navy" data-track="rss_click">RSS</a> · <a href="{{ route('updates.index') }}" class="hover:text-brand-navy">Updates hub</a> · <a href="{{ route('subscribe.show') }}" class="hover:text-brand-navy">Weekly digest</a></p>
    @else
        <p class="py-4 text-sm text-brand-muted">No changes recorded yet. <a href="{{ route('updates.index') }}" class="text-brand-blue hover:underline">See all AI policy updates</a>.</p>
    @endif
</section>
