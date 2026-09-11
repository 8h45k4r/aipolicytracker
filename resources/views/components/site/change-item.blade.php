@props(['change', 'compact' => false])
<article id="{{ $change->slug }}" class="py-4 border-b border-slate-200 last:border-0 scroll-mt-24">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <time datetime="{{ $change->occurred_on->toDateString() }}" class="font-mono text-slate-700">{{ $change->occurred_on->format('j M Y') }}</time>
        <a href="{{ $change->jurisdiction->url() }}" class="font-medium text-slate-700 hover:text-slate-900">{{ $change->jurisdiction->name }}</a>
        @php($impact = $change->impactEnum())
        <span class="badge {{ $impact->value === 'urgent' ? 'bg-rose-50 text-rose-800 ring-rose-600/20' : ($impact->value === 'high' ? 'bg-amber-50 text-amber-800 ring-amber-600/20' : 'bg-slate-100 text-slate-700 ring-slate-500/20') }}">{{ $impact->label() }}</span>
        @if($change->statusAfterEnum())<x-site.status-badge :status="$change->statusAfterEnum()" />@endif
    </div>
    <h3 class="mt-1.5 text-base font-semibold text-slate-900 leading-snug">{{ $change->title }}</h3>
    @unless($compact)
    <p class="mt-1.5 text-sm text-slate-700">{{ $change->what_changed }}</p>
    @if($change->practical_impact)<p class="mt-1.5 text-sm text-slate-700"><span class="font-medium text-slate-900">Practical impact:</span> {{ $change->practical_impact }}</p>@endif
    @endunless
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
        @if($change->policyInstrument)<a href="{{ $change->policyInstrument->url() }}" class="hover:text-slate-900">{{ $change->policyInstrument->short_title ?: $change->policyInstrument->title }}</a>@endif
        @if($change->official_source_url)<a href="{{ $change->official_source_url }}" rel="noopener nofollow" class="text-teal-800 hover:underline" data-track="source_click">{{ $change->source_title ?: 'Official source' }}</a>@endif
        <x-site.verified :record="$change" />
    </div>
</article>
