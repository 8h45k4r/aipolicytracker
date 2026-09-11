@props(['name', 'url', 'license', 'licenseUrl', 'citation' => null, 'note' => null, 'date' => null])
<aside {{ $attributes->merge(['class' => 'rule pt-3 text-xs text-brand-muted leading-5']) }} aria-label="Data attribution">
    <p>
        <span class="font-medium text-brand-navy">Source:</span> <a href="{{ $url }}" rel="noopener">{{ $name }}</a>, licensed <a href="{{ $licenseUrl }}" rel="license noopener">{{ $license }}</a>.
        @if($date) Snapshot {{ $date }}. @endif
        @if($note) {{ $note }} @endif
    </p>
    @if($citation)<p class="mt-1">Cite as: {{ $citation }}</p>@endif
</aside>
