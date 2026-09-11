@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-2">MIT AI Risk Repository</p>
    <h1 class="mt-1 font-display text-3xl font-semibold text-brand-navy">The frameworks behind the risk database</h1>
    <p class="mt-2 max-w-[64ch] text-brand-body">{{ $papers->count() ?: '—' }} documents (taxonomies, frameworks, standards, papers) were synthesised into the repository. The count shows how many risk entries were extracted from each; click a framework to browse its entries.</p>
    <div class="table-wrap mt-6"><table><thead><tr><th scope="col">Framework</th><th scope="col">Reference</th><th scope="col" class="text-right">Risk entries</th></tr></thead><tbody>
        @forelse($papers as $p)<tr><td><a href="{{ route('risk.risks', ['paper' => $p['quick_ref']]) }}">{{ $p['title'] }}</a></td><td class="font-mono text-xs">{{ $p['quick_ref'] }}</td><td class="text-right font-mono tabular-nums">{{ $p['risks'] ?: '—' }}</td></tr>@empty<tr><td colspan="3"><x-site.empty title="Framework list not imported yet" /></td></tr>@endforelse
    </tbody></table></div>
    <x-site.attribution class="mt-10" :name="$mit['source'] ?? 'MIT AI Risk Repository'" :url="$mit['source_url'] ?? 'https://airisk.mit.edu/'" :license="$mit['license'] ?? 'CC BY 4.0'" :licenseUrl="$mit['license_url'] ?? 'https://creativecommons.org/licenses/by/4.0/'" :citation="$mit['citation'] ?? null" />
</div>
@endsection
