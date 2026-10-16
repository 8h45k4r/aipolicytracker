@extends('site.layouts.app')
@section('content')
<div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">AI regulation MCP server for assistants</h1>
    <p class="mt-3 prose-policy">An open-source <a href="https://modelcontextprotocol.io/" rel="noopener">Model Context Protocol</a> server that lets an AI assistant look up AI laws, obligations, deadlines and enforcement actions here, instead of scraping pages. Read-only, no key, and every result carries the record's official source and review status.</p>

    <section class="mt-10" aria-labelledby="install-heading"><h2 id="install-heading" class="section-title">How do I install it?</h2>
        <p class="prose-policy mt-2">It runs on your machine with Node.js and calls the public site; there is no hosted endpoint yet. Get the code, then register the server with your MCP client:</p>
        <pre class="mt-3 overflow-x-auto rounded-md bg-brand-navy p-3 text-xs text-white/80" tabindex="0" role="region" aria-label="Get the code">git clone {{ config('aipolicytracker.github_url') }}.git</pre>
        <pre class="mt-3 overflow-x-auto rounded-md bg-brand-navy p-3 text-xs text-white/80" tabindex="0" role="region" aria-label="Client configuration">{
  "mcpServers": {
    "aipolicytracker": {
      "command": "node",
      "args": ["/path/to/aipolicytracker/agent/server.mjs"]
    }
  }
}</pre>
        <p class="mt-2 text-sm text-brand-muted">No dependencies to install. Source and tests: <a href="{{ config('aipolicytracker.github_url') }}/tree/main/agent" rel="noopener">agent/</a> in the repository.</p>
    </section>

    <section class="mt-8" aria-labelledby="tools-heading"><h2 id="tools-heading" class="section-title">What can an assistant do with it?</h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">MCP tools</caption><thead><tr><th scope="col">Tool</th><th scope="col">Returns</th></tr></thead><tbody>
            @foreach($tools as [$name, $what])<tr><th scope="row" class="font-mono text-xs font-normal">{{ $name }}</th><td>{{ $what }}</td></tr>@endforeach
        </tbody></table></div>
        <p class="mt-2 text-sm text-brand-muted"><code class="rounded bg-brand-paper px-1">corpus_health</code> comes first on purpose: most records are not yet confirmed by a named reviewer, and an assistant should say so before quoting one.</p>
    </section>

    <section class="mt-8" aria-labelledby="more-heading"><h2 id="more-heading" class="section-title">Prefer plain HTTP?</h2>
        <p class="prose-policy mt-2">The same data is in the <a href="{{ route('developers') }}">read-only API</a> and the <a href="{{ route('open-data') }}">bulk exports</a>. For higher limits and service terms, see <a href="{{ route('pricing') }}">plans</a>.</p>
    </section>

    <x-site.faq :items="$seo->faqItems()" />
</div>
@endsection
