{{-- Three things a visitor comes to do, as a path rather than a paragraph: find the rules that
     reach them, see when they apply, and prove compliance. Each step is the page that does it. --}}
@php($steps = [
    ['n' => 1, 'title' => 'Find what applies', 'text' => 'Answer six questions; see the laws and duties that may reach your AI system.', 'href' => route('tools.applicability'), 'cta' => 'Applicability check', 'icon' => 'M11 4a7 7 0 1 0 4.2 12.6l4.1 4.1 1.4-1.4-4.1-4.1A7 7 0 0 0 11 4zm0 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10z'],
    ['n' => 2, 'title' => 'Know the dates', 'text' => 'Every recorded deadline on a list or a month grid, with comment periods and hearings.', 'href' => route('calendar'), 'cta' => 'Deadline calendar', 'icon' => 'M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm-2 8h14v9H5v-9z'],
    ['n' => 3, 'title' => 'Prove it', 'text' => 'Templates and kits built from the duties: registers, notices, assessments, evidence trackers.', 'href' => route('templates.index'), 'cta' => 'Templates library', 'icon' => 'M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1.5V8h4.5L14 3.5zM8 13l2.5 2.5L16 10l1.4 1.4-6.9 6.9L6.6 14.4 8 13z'],
])
<section {{ $attributes->merge(['class' => 'container-site py-8']) }} aria-labelledby="journey-h">
    <h2 id="journey-h" class="section-title">What you can do here</h2>
    <ol class="mt-4 grid gap-4 md:grid-cols-3" aria-label="Three steps">
        @foreach($steps as $s)
        <li class="relative">
            <a href="{{ $s['href'] }}" class="group flex h-full flex-col rounded-xl border border-brand-line bg-white p-5 no-underline transition-colors hover:border-brand-navy" data-track="journey_step_{{ $s['n'] }}">
                <span class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-brand-paper" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-6 w-6 fill-brand-navy"><path d="{{ $s['icon'] }}"/></svg></span>
                    <span><span class="block text-xs font-semibold uppercase tracking-wide text-brand-blue">Step {{ $s['n'] }}</span><span class="block text-lg font-semibold text-brand-navy">{{ $s['title'] }}</span></span>
                </span>
                <span class="mt-3 text-sm text-brand-body">{{ $s['text'] }}</span>
                <span class="mt-auto pt-3 text-sm font-medium text-brand-blue group-hover:underline">{{ $s['cta'] }} &rarr;</span>
            </a>
            @unless($loop->last)
            <svg class="absolute -right-4 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 md:block" viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="15" class="fill-white stroke-brand-line" stroke-width="1.5"/><path d="M13 10l6 6-6 6" fill="none" class="stroke-brand-navy" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @endunless
        </li>
        @endforeach
    </ol>
</section>
