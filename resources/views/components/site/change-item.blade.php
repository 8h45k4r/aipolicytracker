@props(['change', 'compact' => false])
<article id="{{ $change->slug }}" class="py-4 border-b border-brand-line last:border-0 scroll-mt-24">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <time datetime="{{ $change->occurred_on->toDateString() }}" class="datestamp">{{ $change->occurred_on->format('j M Y') }}</time>
        <a href="{{ $change->jurisdiction->url() }}" class="font-medium text-brand-body hover:text-brand-navy">{{ $change->jurisdiction->name }}</a>
        @php($impact = $change->impactEnum())
        <span class="badge {{ $impact->value === 'urgent' ? 'bg-state-badbg text-state-bad ring-state-bad/20' : ($impact->value === 'high' ? 'bg-state-warnbg text-state-warn ring-state-warn/20' : 'bg-state-neutralbg text-brand-body ring-brand-line') }}">{{ $impact->label() }}</span>
        @if($change->statusAfterEnum())<x-site.status-badge :status="$change->statusAfterEnum()" />@endif
    </div>
    <h3 class="mt-1.5 font-display text-lg font-semibold text-brand-navy leading-snug">{{ $change->title }}</h3>
    @unless($compact)
    <p class="mt-1.5 text-sm text-brand-body">{{ $change->what_changed }}</p>
    @if($change->practical_impact)<p class="mt-1.5 text-sm text-brand-body"><span class="font-medium text-brand-navy">Practical impact:</span> {{ $change->practical_impact }}</p>@endif
    @endunless
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-muted">
        @if($change->policyInstrument)<a href="{{ $change->policyInstrument->url() }}" class="hover:text-brand-navy">{{ $change->policyInstrument->short_title ?: $change->policyInstrument->title }}</a>@endif
        @if($change->official_source_url)<a href="{{ $change->official_source_url }}" rel="noopener nofollow" class="text-brand-blue hover:underline" data-track="source_click">{{ $change->source_title ?: 'Official source' }}</a>@endif
        <x-site.verified :record="$change" />
    </div>
</article>
