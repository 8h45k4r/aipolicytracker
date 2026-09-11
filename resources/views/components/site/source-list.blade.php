@props(['sources', 'title' => 'Official sources'])
<section aria-labelledby="sources-heading" {{ $attributes }}>
    <h2 id="sources-heading" class="section-title">{{ $title }}</h2>
    <ol class="mt-3 space-y-3">
        @forelse($sources as $s)
        <li class="text-sm">
            <a href="{{ $s->url ?? $s['url'] }}" rel="noopener" class="font-medium text-brand-blue hover:underline break-words" data-track="source_click">{{ $s->title ?? $s['title'] }}</a>
            <div class="text-xs text-brand-muted">
                {{ $s->publisher ?? $s['publisher'] ?? '' }}
                @php($d = $s->document_date ?? ($s['document_date'] ?? null))
                @if($d)· {{ $d instanceof \DateTimeInterface ? $d->format('j M Y') : $d }}@endif
                @php($tier = $s->source_tier ?? ($s['tier'] ?? null))
                @if($tier)· Tier {{ $tier }} source @endif
            </div>
        </li>
        @empty
        <li class="text-sm text-brand-muted">No official source recorded yet. This record should not be relied on until a source is linked.</li>
        @endforelse
    </ol>
</section>
