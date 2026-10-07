{{--
The same navigation on small screens: one <details> per group, nested inside the menu's own
<details>. Nesting works natively, so this needs no script either. The same five to seven
entries per group as the desktop menu, and the same hub link, so nothing is desktop-only.
--}}
@php
    $groups = \App\Support\Navigation::groups();
    $here = url()->current();
    $urlOf = fn (array $item) => \App\Support\Navigation::url($item);
@endphp

<nav aria-label="Mobile" class="absolute right-0 mt-2 max-h-[80vh] w-[19rem] overflow-y-auto rounded-sm border border-brand-line bg-white p-2 shadow-lg z-40">
    <form action="{{ route('search') }}" method="get" role="search" class="px-1 pb-2 border-b border-brand-line">
        <label for="mobile-q" class="sr-only">Search the site</label>
        <input id="mobile-q" name="q" type="search" class="input" placeholder="Search laws, duties, templates…" autocomplete="off">
    </form>
    @foreach($groups as $key => $group)
        <details class="border-b border-brand-line last:border-0">
            <summary class="flex items-center justify-between gap-2 rounded-sm px-3 py-2.5 text-sm font-semibold text-brand-navy list-none">
                {{ $group['label'] }}
                <svg class="h-3 w-3 text-brand-muted" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <div class="pb-2">
                @foreach(\App\Support\Navigation::menu($group) as $item)
                    <a href="{{ $urlOf($item) }}"
                       class="block rounded-sm px-3 py-2 text-sm text-brand-body no-underline hover:bg-brand-paper {{ $urlOf($item) === $here ? 'bg-brand-paper font-medium text-brand-navy' : '' }}"
                       @if($urlOf($item) === $here) aria-current="page" @endif
                       data-track="nav_click" data-track-label="mobile_{{ $key }}">{{ $item['label'] }}</a>
                @endforeach
                @if(\App\Support\Navigation::hasMore($group))
                    <a href="{{ route('explore', $key) }}" class="block rounded-sm px-3 py-2 text-sm font-medium no-underline hover:bg-brand-paper" data-track="nav_hub_click" data-track-label="mobile_{{ $key }}">Everything in {{ $group['label'] }} &rarr;</a>
                @endif
            </div>
        </details>
    @endforeach
    <div class="mt-2 border-t border-brand-line pt-2">
        <a href="{{ route('subscribe.show') }}" class="block rounded-sm px-3 py-2.5 text-sm font-medium text-brand-navy no-underline hover:bg-brand-paper">Subscribe to the digest</a>
        <a href="{{ route('saved') }}" class="block rounded-sm px-3 py-2.5 text-sm text-brand-body no-underline hover:bg-brand-paper">Saved records</a>
        @auth
        <a href="{{ route('profile.edit') }}" class="block rounded-sm px-3 py-2.5 text-sm text-brand-body no-underline hover:bg-brand-paper">Your account</a>
        <a href="{{ route('following.index') }}" class="block rounded-sm px-3 py-2.5 text-sm text-brand-body no-underline hover:bg-brand-paper">What you watch</a>
        @else
        <a href="{{ route('login') }}" class="block rounded-sm px-3 py-2.5 text-sm text-brand-body no-underline hover:bg-brand-paper">Sign in</a>
        @endauth
    </div>
</nav>
