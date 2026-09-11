@props(['policy'])
<article class="py-4 border-b border-slate-200 last:border-0">
    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <a href="{{ $policy->jurisdiction->url() }}" class="font-medium text-slate-700 hover:text-slate-900">{{ $policy->jurisdiction->name }}</a>
        <span aria-hidden="true">·</span>
        <span>{{ $policy->typeEnum()->label() }}</span>
        <x-site.status-badge :status="$policy->statusEnum()" />
        @if($policy->is_binding)<span class="badge bg-slate-900 text-white ring-slate-900">Binding</span>@endif
    </div>
    <h3 class="mt-1.5 text-base font-semibold text-slate-900 leading-snug"><a href="{{ $policy->url() }}" class="hover:underline">{{ $policy->short_title ?: $policy->title }}</a></h3>
    @if($policy->short_title && $policy->short_title !== $policy->title)<p class="text-xs text-slate-500">{{ $policy->title }}</p>@endif
    <p class="mt-2 text-sm text-slate-700 line-clamp-3">{{ $policy->summary_plain }}</p>
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
        @if($policy->applies_from)<span>Applies from {{ $policy->applies_from->format('j M Y') }}</span>@elseif($policy->in_force_on)<span>In force {{ $policy->in_force_on->format('j M Y') }}</span>@elseif($policy->adopted_on)<span>Adopted {{ $policy->adopted_on->format('j M Y') }}</span>@endif
        <x-site.verified :record="$policy" />
        @if($policy->official_source_url)<a href="{{ $policy->official_source_url }}" rel="noopener nofollow" class="text-teal-800 hover:underline" data-track="source_click">Official source</a>@endif
    </div>
</article>
