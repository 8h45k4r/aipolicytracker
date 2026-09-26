@props(['policy'])
<article class="py-4 border-b border-brand-line last:border-0">
    <div class="flex flex-wrap items-center gap-2 text-xs text-brand-muted">
        <a href="{{ $policy->jurisdiction->url() }}" class="font-medium text-brand-body hover:text-brand-navy">{{ $policy->jurisdiction->name }}</a>
        <span aria-hidden="true">·</span>
        <span>{{ $policy->typeEnum()->label() }}</span>
        <x-site.status-badge :status="$policy->statusEnum()" />
        @if($policy->is_binding)<span class="badge-binding">Binding</span>@endif
    </div>
    <h3 class="mt-1.5 font-display text-lg font-semibold text-brand-navy leading-snug"><a href="{{ $policy->url() }}" class="text-brand-navy no-underline hover:underline">{{ $policy->short_title ?: $policy->title }}</a></h3>
    @if($policy->short_title && $policy->short_title !== $policy->title)<p class="text-xs text-brand-muted">{{ $policy->title }}</p>@endif
    @if($policy->title_native)<p class="text-xs text-brand-muted">{{ $policy->title_native }}</p>@endif
    <p class="mt-2 text-sm text-brand-body line-clamp-3">{{ $policy->summary_plain }}</p>
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-muted">
        @if($policy->applies_from)<span>Applies from {{ $policy->applies_from->format('j M Y') }}</span>@elseif($policy->in_force_on)<span>In force {{ $policy->in_force_on->format('j M Y') }}</span>@elseif($policy->adopted_on)<span>Adopted {{ $policy->adopted_on->format('j M Y') }}</span>@endif
        <x-site.verified :record="$policy" />
        @if($policy->official_source_url)<a href="{{ $policy->official_source_url }}" rel="noopener nofollow" class="text-brand-blue hover:underline" data-track="source_click">Official source</a>@endif
    </div>
</article>
