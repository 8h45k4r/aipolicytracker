@props(['items', 'title' => 'Frequently asked questions'])
@if(!empty($items))
<section aria-labelledby="faq-heading" class="mt-10">
    <h2 id="faq-heading" class="section-title">{{ $title }}</h2>
    <dl class="mt-3 divide-y divide-brand-line border-y border-brand-line">
        @foreach($items as $item)
        <div class="py-3">
            <dt class="font-medium text-brand-navy">{{ $item['question'] }}</dt>
            <dd class="mt-1 text-sm text-brand-body">{{ trim($item['answer']) }}</dd>
        </div>
        @endforeach
    </dl>
</section>
@endif
