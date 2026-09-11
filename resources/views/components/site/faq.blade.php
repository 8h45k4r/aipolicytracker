@props(['items', 'title' => 'Frequently asked questions'])
@if(!empty($items))
<section aria-labelledby="faq-heading" class="mt-10">
    <h2 id="faq-heading" class="section-title">{{ $title }}</h2>
    <dl class="mt-3 divide-y divide-slate-200 border-y border-slate-200">
        @foreach($items as $item)
        <div class="py-3">
            <dt class="font-medium text-slate-900">{{ $item['question'] }}</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ trim($item['answer']) }}</dd>
        </div>
        @endforeach
    </dl>
</section>
@endif
