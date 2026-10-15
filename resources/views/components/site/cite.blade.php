@props(['title', 'url', 'sourceUrl' => null, 'sourceTitle' => null, 'publisher' => null, 'record' => null, 'reviewer' => null, 'verifiedAt' => null])
{{--
    How to cite this record. A reader who quotes the site in a filing or a paper
    needs the record's address, the date they read it and the official text it
    rests on; an answer engine needs the same three things before it attributes.
    The format is the one config/aipolicytracker.php publishes for the dataset.

    The DOI line appears only once a dataset DOI has been minted (DATASET_DOI).
    It is the corpus DOI, so the record is cited as part of that dataset, never
    as if the DOI were the record's own.
--}}
@php($doiUrl = \App\Support\DatasetCitation::doiUrl())
{{-- With a record: its permanent id (whose address survives a rename), the record
     version and who verified it when, so a citation says exactly which state of the
     record was relied on. --}}
@php($aiptId = data_get($record, 'aipt_id'))
@php($permalink = $aiptId ? route('record.id', $aiptId) : $url)
@php($version = data_get($record, 'content_version'))
@php($verifiedAt = $verifiedAt ?? data_get($record, 'last_verified_at'))
@php($verifiedBy = $reviewer ?? data_get($record, 'reviewed_by'))
@php($stamp = array_values(array_filter([$aiptId, $version ? 'record version '.$version : null, $verifiedAt ? 'verified '.$verifiedAt->format('j F Y').($verifiedBy ? ' by '.$verifiedBy : '') : 'not yet verified'])))
<section aria-labelledby="cite-heading" {{ $attributes }}>
    <h2 id="cite-heading" class="section-title">Cite this record</h2>
    <p class="mt-2 text-sm text-brand-body break-words" data-cite>{{ config('aipolicytracker.site_name') }} ({{ now()->year }}). &ldquo;{{ $title }}&rdquo;@if($record) [{{ implode(', ', $stamp) }}]@endif. <a href="{{ $permalink }}">{{ $permalink }}</a> (accessed {{ now()->format('j F Y') }}). Data licensed {{ config('aipolicytracker.data_license') }}.@if($doiUrl) Part of the {{ config('aipolicytracker.site_name') }} dataset, <a href="{{ $doiUrl }}" rel="noopener" data-cite-doi>{{ $doiUrl }}</a>.@endif</p>
    @if($aiptId)
    <p class="mt-1 text-xs text-brand-muted">Permanent id <span class="datestamp" data-aipt-id>{{ $aiptId }}</span>: it never changes or gets reused, and its address keeps working if this page moves.</p>
    @endif
    @if($sourceUrl)
    <p class="mt-1 text-xs text-brand-muted">Cite the official text alongside it: {{ $sourceTitle ? $sourceTitle.', ' : '' }}{{ $publisher ? $publisher.', ' : '' }}<a href="{{ $sourceUrl }}" rel="noopener" class="break-all">{{ $sourceUrl }}</a>.</p>
    @endif
    <details class="mt-2 text-xs">
        <summary class="cursor-pointer text-brand-blue">BibTeX</summary>
        <pre class="mt-2 overflow-x-auto rounded-md bg-brand-paper p-3 text-brand-body" data-cite-bibtex>{{ \App\Support\DatasetCitation::bibtex($title, $url) }}</pre>
    </details>
</section>
