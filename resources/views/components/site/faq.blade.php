@props(['items', 'title' => 'Frequently asked questions'])
@if(!empty($items))
<section aria-labelledby="faq-heading" class="mt-10">
    <h2 id="faq-heading" class="section-title">{{ $title }}</h2>
    <div class="mt-3 divide-y divide-brand-line border-y border-brand-line">
        @foreach($items as $item)
        <div class="py-3">
            <h3 class="font-medium text-brand-navy">{{ $item['question'] }}</h3>
            <p class="mt-1 text-sm text-brand-body">{{ trim($item['answer']) }}</p>
        </div>
        @endforeach
    </div>
</section>
@endif
