@props(['groups', 'title', 'aLabel', 'bLabel', 'note' => null])
{{--
Two shares side by side for each category, in percent, grouped under a heading.
$groups: ['Intent' => ['Deliberate' => [a, b], ...]]. Used to set what is recorded
against what is described.
--}}
<figure {{ $attributes->merge(['class' => 'card-flat p-4']) }}>
    <figcaption class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><span class="text-sm font-medium text-brand-navy">{{ $title }}</span>@if($note)<span class="meta">{{ $note }}</span>@endif</figcaption>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-body" aria-label="Legend">
        <li class="inline-flex items-center gap-1.5"><span aria-hidden="true" class="inline-block h-2.5 w-2.5 bg-brand-navy"></span>{{ $aLabel }}</li>
        <li class="inline-flex items-center gap-1.5"><span aria-hidden="true" class="inline-block h-2.5 w-2.5 bg-brand-blue"></span>{{ $bLabel }}</li>
    </ul>
    <div class="mt-3 grid gap-5 sm:grid-cols-3">
        @foreach($groups as $group => $rows)
        <div>
            <p class="eyebrow">{{ $group }}</p>
            <dl class="mt-2 space-y-3 text-xs">
                @foreach($rows as $label => [$a, $b])
                <div>
                    <dt class="text-brand-body">{{ $label }}</dt>
                    <dd class="mt-1 flex items-center gap-2" data-tip="{{ $label }}: {{ $a }}% of {{ mb_strtolower($aLabel) }}"><span class="block h-2.5 bg-brand-navy" style="width: {{ max(1, round($a * 0.8)) }}%"></span><span class="tabular-nums text-brand-navy">{{ $a }}%</span><span class="sr-only">{{ $aLabel }}</span></dd>
                    <dd class="mt-1 flex items-center gap-2" data-tip="{{ $label }}: {{ $b }}% of {{ mb_strtolower($bLabel) }}"><span class="block h-2.5 bg-brand-blue" style="width: {{ max(1, round($b * 0.8)) }}%"></span><span class="tabular-nums text-brand-muted">{{ $b }}%</span><span class="sr-only">{{ $bLabel }}</span></dd>
                </div>
                @endforeach
            </dl>
        </div>
        @endforeach
    </div>
</figure>
