{{--
Primary navigation, driven by config/navigation.php.

Each group is a <details> element, so the menu opens, closes and takes keyboard focus with
JavaScript disabled. public.js only adds Escape-to-close, close-on-outside-click and
closing a sibling when another group opens.

A group is marked current when one of its links is the page being viewed. Matching is done
on the resolved URL rather than the route name, because nine landing pages share one route
name and would otherwise all report themselves as current.
--}}
@php
    $groups = config('navigation.primary', []);
    $here = url()->current();
    $urlOf = fn (array $item) => route($item['route'], $item['params'] ?? []);
    $isHere = fn (array $item) => $urlOf($item) === $here;
    $groupIsHere = fn (array $group) => collect($group['sections'])
        ->flatMap(fn ($s) => $s['items'])
        ->contains(fn ($item) => $isHere($item));
@endphp

<nav aria-label="Primary" class="hidden lg:flex items-center gap-1" data-nav>
    @foreach($groups as $key => $group)
        @php($current = $groupIsHere($group))
        @php($wide = count($group['sections']) > 1)
        <details class="relative" data-nav-group>
            <summary class="nav-link list-none gap-1 {{ $current ? 'nav-link-active' : '' }}" aria-haspopup="true">
                {{ $group['label'] }}
                <svg class="h-3 w-3 shrink-0 text-brand-muted transition-transform" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <div class="nav-panel {{ $wide ? 'w-[34rem]' : 'w-72' }}">
                @if(! empty($group['summary']))
                    <p class="px-3 pb-2 text-xs text-brand-muted">{{ $group['summary'] }}</p>
                @endif
                <div class="{{ $wide ? 'grid grid-cols-2 gap-x-4' : '' }}">
                    @foreach($group['sections'] as $section)
                        <div>
                            @if(! empty($section['heading']))
                                <p class="px-3 pt-1 pb-1.5 eyebrow">{{ $section['heading'] }}</p>
                            @endif
                            <ul>
                                @foreach($section['items'] as $item)
                                    <li>
                                        <a href="{{ $urlOf($item) }}"
                                           class="block rounded-sm px-3 py-2 no-underline hover:bg-brand-paper {{ $isHere($item) ? 'bg-brand-paper' : '' }}"
                                           @if($isHere($item)) aria-current="page" @endif
                                           data-track="nav_click" data-track-label="{{ $key }}">
                                            <span class="block text-sm font-medium text-brand-navy">{{ $item['label'] }}</span>
                                            @if(! empty($item['note']))
                                                <span class="block text-xs text-brand-muted">{{ $item['note'] }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        </details>
    @endforeach
</nav>
