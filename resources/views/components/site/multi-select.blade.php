@props(['name', 'label', 'options', 'selected' => []])
{{-- Compact multi-select: a dropdown button that opens a checkbox panel. Works without JavaScript (the form's Apply button submits); with JavaScript the form submits when the panel closes. --}}
@php($count = count($selected))
<details class="relative" data-multi-select>
    <summary class="flex min-h-[44px] w-full items-center justify-between gap-2 rounded-sm border border-brand-line bg-white px-3 py-2 text-sm text-brand-body cursor-pointer list-none select-none hover:border-brand-navy focus:outline-none focus:ring-2 focus:ring-brand-cyan" aria-label="{{ $label }} filter">
        <span class="truncate">{{ $count ? $count.' selected' : 'All' }}</span>
        <svg class="h-4 w-4 shrink-0 text-brand-muted" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </summary>
    <div class="absolute left-0 z-30 mt-1 w-56 rounded-sm border border-brand-line bg-white p-2 shadow-lg">
        @foreach($options as $value => $text)
        <label class="flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm text-brand-body hover:bg-brand-paper"><input type="checkbox" name="{{ $name }}[]" value="{{ $value }}" class="rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy" @checked(in_array($value, $selected, true))>{{ $text }}</label>
        @endforeach
        @if($count)<a href="#" class="mt-1 block px-2 py-1 text-xs text-brand-muted hover:text-brand-navy" data-multi-clear>Clear</a>@endif
    </div>
</details>
