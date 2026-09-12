@props(['subjectType', 'subjectSlug', 'saveTitle' => null, 'saveUrl' => null, 'saveMeta' => null])
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2 text-sm']) }}>
    @if(in_array($subjectType, \App\Models\Follow::TYPES, true))<x-site.follow-button :type="$subjectType" :slug="$subjectSlug" />@endif
    @if($saveTitle)<x-site.save-button :type="$subjectType" :slug="$subjectSlug" :title="$saveTitle" :url="$saveUrl" :meta="$saveMeta" />@endif
    <a href="{{ route('contribute', ['type' => 'correction', 'subject_type' => $subjectType, 'subject_slug' => $subjectSlug]) }}" class="btn-secondary" data-track="correction_click">Report a correction</a>
    <a href="{{ route('methodology') }}" class="btn-secondary">How we verify</a>
    <button type="button" class="btn-secondary" data-copy-link>Copy link</button>
    <button type="button" class="btn-secondary" data-share hidden>Share</button>
</div>
