@props(['items', 'variant' => 'sidebar'])
{{--
"On this page": a list of in-page links built by the caller from the sections it
actually renders (id => label), so the list never points at a section the record
does not have. Plain anchors: it works without JavaScript.
  sidebar  a nav for the desktop sidebar (the caller makes it sticky);
  mobile   a compact <details> jump menu, shown under lg.
--}}
@if(count($items) > 1)
@if($variant === 'mobile')
<details {{ $attributes->merge(['class' => 'card-flat text-sm lg:hidden']) }} data-toc="mobile">
    <summary class="flex min-h-[44px] cursor-pointer items-center justify-between gap-2 px-4 font-medium text-brand-navy">On this page <span class="text-xs font-normal text-brand-muted">{{ count($items) }} sections</span></summary>
    <nav aria-label="On this page" class="border-t border-brand-line px-4 py-2">
        <ol class="space-y-1">
            @foreach($items as $id => $label)<li><a href="#{{ $id }}" class="block py-1.5 text-brand-body hover:text-brand-navy">{{ $label }}</a></li>@endforeach
        </ol>
    </nav>
</details>
@else
<nav aria-labelledby="toc-heading" {{ $attributes->merge(['class' => 'hidden lg:block text-sm']) }} data-toc="sidebar">
    <p id="toc-heading" class="font-semibold text-brand-navy">On this page</p>
    <ol class="mt-2 space-y-1 border-l border-brand-line">
        @foreach($items as $id => $label)<li><a href="#{{ $id }}" class="-ml-px block border-l border-transparent py-1 pl-3 text-brand-body hover:border-brand-navy hover:text-brand-navy">{{ $label }}</a></li>@endforeach
    </ol>
</nav>
@endif
@endif
