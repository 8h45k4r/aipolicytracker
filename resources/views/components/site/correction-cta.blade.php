@props(['subjectType', 'subjectSlug', 'saveTitle' => null, 'saveUrl' => null, 'saveMeta' => null, 'sourceUrl' => null])
{{-- Record actions. With a source URL, "Open official source" is the primary button and Save and Follow
     are secondary; without one, Save is primary. Sharing, corrections and the method are text links under them. --}}
<div {{ $attributes->merge(['class' => 'text-sm']) }} data-record-actions>
    <div class="flex flex-wrap gap-2">
        @if($sourceUrl)<a href="{{ $sourceUrl }}" rel="noopener" class="btn-primary" data-track="source_click" data-primary-source>Open official source</a>@endif
        @if($saveTitle)<x-site.save-button :type="$subjectType" :slug="$subjectSlug" :title="$saveTitle" :url="$saveUrl" :meta="$saveMeta" :primary="! $sourceUrl" class="min-w-[6rem]" />@endif
        @if(in_array($subjectType, \App\Models\Follow::TYPES, true))<x-site.follow-button :type="$subjectType" :slug="$subjectSlug" />@endif
    </div>
    <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px]">
        <button type="button" class="text-brand-blue hover:underline" data-copy-link>Copy link</button>
        <button type="button" class="text-brand-blue hover:underline" data-share hidden>Share</button>
        <a href="{{ route('contribute', ['type' => 'correction', 'subject_type' => $subjectType, 'subject_slug' => $subjectSlug]) }}" data-track="correction_click">Report a correction</a>
        <a href="{{ route('methodology') }}">How we verify</a>
    </p>
</div>
