@props(['items', 'title' => 'Frequently asked questions', 'collapsible' => false])
@if(!empty($items))
{{-- Marks the request so the layout does not print the same questions a second time. --}}
@php(request()->attributes->set('faq.rendered', true))
<section aria-labelledby="faq-heading" class="mt-10" @if($collapsible) data-faq-collapsed @endif>
    <h2 id="faq-heading" class="section-title">{{ $title }}</h2>
    @if($collapsible)
    {{-- Each answer sits in a native <details>: still in the page (and in its FAQ markup), opened on demand, no JavaScript. --}}
    <div class="mt-3 divide-y divide-brand-line border-y border-brand-line">
        @foreach($items as $item)
        <details class="group py-1">
            <summary class="flex min-h-[44px] cursor-pointer list-none items-center justify-between gap-3 py-2"><h3 class="font-medium text-brand-navy">{{ $item['question'] }}</h3><span aria-hidden="true" class="text-brand-muted transition-transform group-open:rotate-90">&rsaquo;</span></summary>
            <p class="pb-3 text-sm text-brand-body">{{ trim($item['answer']) }}</p>
        </details>
        @endforeach
    </div>
    @else
    <div class="mt-3 divide-y divide-brand-line border-y border-brand-line">
        @foreach($items as $item)
        <div class="py-3">
            <h3 class="font-medium text-brand-navy">{{ $item['question'] }}</h3>
            <p class="mt-1 text-sm text-brand-body">{{ trim($item['answer']) }}</p>
        </div>
        @endforeach
    </div>
    @endif
</section>
@endif
