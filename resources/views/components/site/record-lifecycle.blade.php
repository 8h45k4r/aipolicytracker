{{--
How a record gets onto the site and stays current, as five steps instead of a paragraph. Every
label is one the methodology page defines below it; the figure adds no rule of its own.
--}}
@php($steps = [
    ['title' => 'Official source', 'text' => 'A record starts from the official document or page, never from commentary.', 'tone' => 'paper',
        'icon' => 'M12 3 3 7v2h18V7l-9-4zM5 11v6h2v-6H5zm4 0v6h2v-6H9zm4 0v6h2v-6h-2zm4 0v6h2v-6h-2zM3 19v2h18v-2H3z'],
    ['title' => 'Record written', 'text' => 'Summarised in our own words, with article references. A field the source does not settle is left empty.', 'tone' => 'paper',
        'icon' => 'M4 17.2V20h2.8l8.3-8.3-2.8-2.8L4 17.2zM17.7 9.3a1 1 0 0 0 0-1.4l-1.6-1.6a1 1 0 0 0-1.4 0l-1.3 1.3 2.8 2.8 1.5-1.1z'],
    ['title' => 'Source-linked', 'text' => 'Published with its source, and labelled Source-linked or Pending review until a person has checked it.', 'tone' => 'warn',
        'icon' => 'M10.6 13.4a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.1 1.1 1.4 1.4 1.1-1.1a2 2 0 1 1 2.9 2.9l-3 3a2 2 0 0 1-2.9 0l-1.4 1.4zm2.8-2.8a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.1-1.1-1.4-1.4-1.1 1.1a2 2 0 1 1-2.9-2.9l3-3a2 2 0 0 1 2.9 0l1.4-1.4z'],
    ['title' => 'Verified', 'text' => 'A named reviewer with declared interests opens the source and confirms each field.', 'tone' => 'good',
        'icon' => 'M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3zm-1.2 14.2L7 12.4l1.4-1.4 2.4 2.4 4.8-4.8L17 10l-6.2 6.2z'],
    ['title' => 'Re-checked', 'text' => 'Each kind of record has a maximum age, and a random sample gets a second reviewer every quarter.', 'tone' => 'navy',
        'icon' => 'M12 5V2L8 6l4 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7z'],
])
@php($tones = ['paper' => 'bg-brand-paper text-brand-navy', 'warn' => 'bg-state-warnbg text-state-warn', 'good' => 'bg-state-goodbg text-state-good', 'navy' => 'bg-brand-navy text-white'])
<section {{ $attributes->merge(['class' => 'mt-8']) }} aria-labelledby="lifecycle-h" data-record-lifecycle>
    <h2 id="lifecycle-h" class="section-title">The life of a record</h2>
    <ol class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach($steps as $i => $s)
        <li class="relative rounded-xl border border-brand-line bg-white p-4">
            <span class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $tones[$s['tone']] }}" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-5 w-5 fill-current"><path d="{{ $s['icon'] }}"/></svg></span>
                <span class="text-xs font-semibold uppercase tracking-wide text-brand-blue">Step {{ $i + 1 }}</span>
            </span>
            <span class="mt-2 block font-semibold text-brand-navy">{{ $s['title'] }}</span>
            <span class="mt-1 block text-sm text-brand-body">{{ $s['text'] }}</span>
            @unless($loop->last)<svg class="absolute -right-3 top-8 z-10 hidden h-5 w-5 lg:block" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="9.5" class="fill-white stroke-brand-line"/><path d="M8 6l4 4-4 4" fill="none" class="stroke-brand-navy" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>@endunless
        </li>
        @endforeach
    </ol>
    <p class="mt-3 text-sm text-brand-muted">The rules behind each step are set out below; the current standing is on the <a href="{{ route('verification') }}">verification policy</a> page.</p>
</section>
