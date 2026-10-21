@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI regulation API and dataset for developers</h1>
    <p class="mt-3 prose-policy">A read-only JSON API and bulk exports covering {{ number_format($stats['jurisdictions']) }} jurisdictions, {{ number_format($stats['policies']) }} policy instruments and {{ number_format($stats['obligations']) }} obligations, each linked to its official source and carrying its review status. Free, no key, under {{ config('aipolicytracker.data_license') }}, which allows commercial use with attribution.</p>
    <div class="mt-5 flex flex-wrap gap-2"><a href="{{ url('/api/v1') }}" class="btn-primary" data-track="api_docs_view">API root</a><a href="{{ route('openapi') }}" class="btn-secondary">OpenAPI document</a><a href="{{ route('open-data') }}" class="btn-secondary">Bulk data</a><a href="{{ route('mcp') }}" class="btn-secondary">MCP server</a></div>

    <section class="mt-10" aria-labelledby="start-heading"><h2 id="start-heading" class="section-title">How do I call the API?</h2>
        <p class="prose-policy mt-2">Send a GET request; no key or sign-up is needed. Responses are JSON, paginated, and every record carries <code class="rounded bg-brand-paper px-1">official_source_url</code>, <code class="rounded bg-brand-paper px-1">review_status</code> and <code class="rounded bg-brand-paper px-1">last_verified_at</code>, so you can show your users how settled each fact is.</p>
        <pre class="mt-3 overflow-x-auto rounded-md bg-brand-navy p-3 text-xs text-white/80" tabindex="0" role="region" aria-label="Example request">curl "{{ url('/api/v1/policies') }}?jurisdiction=eu"</pre>
        <p class="mt-2 text-sm text-brand-muted">Limit: {{ $publicRate }} requests a minute per address, no key needed.@if($paidRate) On the Builder plan, send your account token as <code>Authorization: Bearer …</code> for {{ $paidRate }} a minute.@endif For webhook and delta feeds, see <a href="{{ route('pricing') }}">plans</a>.</p>
    </section>

    <section class="mt-8" aria-labelledby="endpoints-heading"><h2 id="endpoints-heading" class="section-title">Which endpoints are there?</h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Read-only API endpoints</caption><thead><tr><th scope="col">Endpoint</th><th scope="col">Returns</th></tr></thead><tbody>
            @foreach($endpoints as [$path, $what])<tr><th scope="row" class="font-mono text-xs font-normal"><a href="{{ url($path) }}">{{ $path }}</a></th><td>{{ $what }}</td></tr>@endforeach
        </tbody></table></div>
        <p class="mt-2 text-sm text-brand-muted">Single records are at the same path with the slug (<code class="rounded bg-brand-paper px-1">/api/v1/policies/eu-ai-act</code>). The full contract, parameters included, is in the <a href="{{ route('openapi') }}">OpenAPI document</a>.</p>
    </section>

    <section class="mt-8" aria-labelledby="bulk-heading"><h2 id="bulk-heading" class="section-title">Can I download everything at once?</h2>
        <p class="prose-policy mt-2">Yes. The <a href="{{ route('open-data') }}">open data page</a> serves the whole dataset as one JSON bundle, and each record type as CSV and NDJSON, with the JSON Schemas the records are validated against and citation guidance.</p>
    </section>

    <section class="mt-8" aria-labelledby="licence-heading"><h2 id="licence-heading" class="section-title">Can I use it in a commercial product?</h2>
        <p class="prose-policy mt-2">Yes. The data is published under <a href="{{ config('aipolicytracker.data_license_url') }}" rel="noopener">{{ config('aipolicytracker.data_license') }}</a>: use it, change it and sell products built on it, with attribution. <a href="{{ route('pricing') }}">Paid plans</a> add service terms the licence does not, such as a freshness commitment and delta feeds.</p>
    </section>

    <x-site.faq :items="$seo->faqItems()" />
</div>
@endsection
