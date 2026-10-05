@props(['kind', 'key', 'limit' => 2, 'title' => 'Check where you stand'])
{{-- The free self-assessments that fit this page, if any. Kept to two, and it stands in
     for the Certifyi note on the same page (certifyi-cta skips itself), so a page never
     carries two mentions of a related product. --}}
@php($items = \App\Services\Assessments\AssessmentCatalog::for($kind, $key, $limit))
@if($items->isNotEmpty())
@php(request()->attributes->set('assessments.shown', true))
<section {{ $attributes->merge(['class' => 'rounded-sm border border-brand-line bg-brand-paper p-4 text-sm']) }} aria-label="Free self-assessments">
    <p class="font-semibold text-brand-navy">{{ $title }}</p>
    <ul class="mt-2 space-y-2">
        @foreach($items as $a)
        <li><a href="{{ $a['url'] }}" rel="noopener" class="font-medium" data-track="assessment_click" data-track-label="{{ $a['slug'] }}">{{ $a['title'] }}</a><span class="block text-xs text-brand-muted">{{ $a['questions'] }} questions · about {{ $a['minutes'] }} min</span></li>
        @endforeach
    </ul>
    <p class="mt-3 text-xs text-brand-muted"><a href="{{ route('assessments.index') }}">All {{ \App\Services\Assessments\AssessmentCatalog::all()->count() }} self-assessments</a> · on Certifyi, a related product · not an audit</p>
</section>
@endif
