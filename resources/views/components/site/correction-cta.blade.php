@props(['subjectType', 'subjectSlug'])
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2 text-sm']) }}>
    <a href="{{ route('contribute', ['type' => 'correction', 'subject_type' => $subjectType, 'subject_slug' => $subjectSlug]) }}" class="btn-secondary" data-track="correction_click">Report a correction</a>
    <a href="{{ route('methodology') }}" class="btn-secondary">How we verify</a>
    <button type="button" class="btn-secondary" data-copy-link>Copy link</button>
    <button type="button" class="btn-secondary" data-share hidden>Share</button>
</div>
