@props(['title', 'url', 'sourceUrl' => null, 'sourceTitle' => null, 'publisher' => null])
{{--
    How to cite this record. A reader who quotes the site in a filing or a paper
    needs the record's address, the date they read it and the official text it
    rests on; an answer engine needs the same three things before it attributes.
    The format is the one config/aipolicytracker.php publishes for the dataset.
--}}
<section aria-labelledby="cite-heading" {{ $attributes }}>
    <h2 id="cite-heading" class="section-title">Cite this record</h2>
    <p class="mt-2 text-sm text-brand-body break-words" data-cite>{{ config('aipolicytracker.site_name') }} ({{ now()->year }}). &ldquo;{{ $title }}&rdquo;. <a href="{{ $url }}">{{ $url }}</a> (accessed {{ now()->format('j F Y') }}). Data licensed {{ config('aipolicytracker.data_license') }}.</p>
    @if($sourceUrl)
    <p class="mt-1 text-xs text-brand-muted">Cite the official text alongside it: {{ $sourceTitle ? $sourceTitle.', ' : '' }}{{ $publisher ? $publisher.', ' : '' }}<a href="{{ $sourceUrl }}" rel="noopener" class="break-all">{{ $sourceUrl }}</a>.</p>
    @endif
</section>
