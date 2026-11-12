@props(['obligation', 'controls', 'templates' => null])
{{--
A duty as a chain rather than a table: the law it comes from, who it binds, the controls that
meet it, the evidence a reviewer expects and the templates that cover it. Each link is a count
from the record and jumps to that section; a link with nothing recorded is shown as empty, not
hidden, so a gap is visible.
--}}
@php
    $actors = $obligation->terms->where('taxonomy', 'actor');
    $evidence = $obligation->evidenceArtifacts;
    $tcount = $templates ? $templates->count() : 0;
    $steps = [
        ['label' => 'The law', 'value' => $obligation->policyInstrument->short_title ?: \Illuminate\Support\Str::limit($obligation->policyInstrument->title, 34), 'sub' => $obligation->source_reference, 'href' => $obligation->policyInstrument->url(), 'on' => true,
            'icon' => 'M12 3 3 7v2h18V7l-9-4zM5 11v6h2v-6H5zm4 0v6h2v-6H9zm4 0v6h2v-6h-2zm4 0v6h2v-6h-2zM3 19v2h18v-2H3z'],
        ['label' => 'Binds', 'value' => $actors->count() ? $actors->count().' '.\Illuminate\Support\Str::plural('role', $actors->count()) : 'Not recorded', 'sub' => $actors->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::before($n, ' /'))->take(2)->implode(', '), 'href' => '#who-heading', 'on' => $actors->isNotEmpty(),
            'icon' => 'M16 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm0 2c-2.3 0-7 1.2-7 3.5V19h14v-2.5C15 14.2 10.3 13 8 13zm8 0c-.3 0-.6 0-1 .1a4.2 4.2 0 0 1 2 3.4V19h6v-2.5c0-2.3-4.7-3.5-7-3.5z'],
        ['label' => 'Controls', 'value' => $controls->count() ? $controls->count().' recorded' : 'None yet', 'sub' => 'what to put in place', 'href' => '#controls-heading', 'on' => $controls->isNotEmpty(),
            'icon' => 'M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3zm-1.2 14.2L7 12.4l1.4-1.4 2.4 2.4 4.8-4.8L17 10l-6.2 6.2z'],
        ['label' => 'Evidence', 'value' => $evidence->count() ? $evidence->count().' '.\Illuminate\Support\Str::plural('example', $evidence->count()) : 'None yet', 'sub' => 'what a reviewer expects', 'href' => '#evidence-heading', 'on' => $evidence->isNotEmpty(),
            'icon' => 'M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1.5V8h4.5L14 3.5zM8 12v2h8v-2H8zm0 4v2h6v-2H8z'],
        ['label' => 'Templates', 'value' => $tcount ? $tcount.' to start from' : 'None yet', 'sub' => 'free, built from the record', 'href' => $tcount ? '#templates-heading' : route('templates.index'), 'on' => $tcount > 0,
            'icon' => 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm0 2v4h14V5H5zm0 6v8h6v-8H5zm8 0v8h6v-8h-6z'],
    ];
@endphp
<section {{ $attributes->merge(['class' => 'rounded-xl border border-brand-line bg-brand-paper p-4']) }} aria-labelledby="chain-h" data-duty-chain>
    <h2 id="chain-h" class="text-sm font-semibold text-brand-navy">From the law to the evidence <span class="font-normal text-brand-muted">· what this record holds</span></h2>
    <ol class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
        @foreach($steps as $s)
        <li class="relative @if($loop->first) col-span-2 sm:col-span-1 @endif">
            <a href="{{ $s['href'] }}" class="flex h-full flex-col rounded-lg border bg-white p-3 no-underline transition-colors hover:border-brand-navy {{ $s['on'] ? 'border-brand-line' : 'border-dashed border-brand-line' }}" data-track="duty_chain_step">
                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide {{ $s['on'] ? 'text-brand-blue' : 'text-brand-muted' }}"><svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 fill-current" aria-hidden="true"><path d="{{ $s['icon'] }}"/></svg>{{ $s['label'] }}</span>
                <span class="mt-1.5 text-sm font-semibold leading-snug {{ $s['on'] ? 'text-brand-navy' : 'text-brand-muted' }}">{{ $s['value'] }}</span>
                @if($s['sub'])<span class="mt-0.5 text-xs text-brand-muted">{{ $s['sub'] }}</span>@endif
            </a>
            @unless($loop->last)<svg class="absolute -right-2.5 top-1/2 z-10 hidden h-4 w-4 -translate-y-1/2 sm:block" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="7.5" class="fill-white stroke-brand-line"/><path d="M6.5 4.5 10 8l-3.5 3.5" fill="none" class="stroke-brand-navy" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>@endunless
        </li>
        @endforeach
    </ol>
</section>
