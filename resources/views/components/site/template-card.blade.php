@props(['item'])
{{-- One template on the hub and in "related" lists: type, formats, title, what it is, version. --}}
@php($v = $item['version'] ?? null)
<li class="card-flat p-4 flex flex-col">
    <div class="flex flex-wrap gap-1.5">
        <span class="badge bg-brand-navy text-white ring-brand-navy">{{ \App\Services\Templates\TemplateCatalog::typeLabel($item['type']) }}</span>
        <span class="badge bg-state-goodbg text-state-good ring-state-good/30">{{ \App\Services\Templates\TemplateCatalog::formatList($item) }}</span>
        @foreach(($item['frameworks'] ?? []) as $f)<span class="badge-neutral">{{ \App\Services\Templates\TemplateCatalog::frameworkLabel($f) }}</span>@endforeach
    </div>
    <a href="{{ route('templates.show', $item['slug']) }}" class="mt-3 text-lg font-semibold text-brand-navy hover:underline">{{ $item['title'] }}</a>
    <p class="mt-1 text-sm text-brand-body flex-1">{{ $item['short'] }}</p>
    <p class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm"><a href="{{ route('templates.show', $item['slug']) }}" class="font-medium text-brand-navy">Preview and download →</a>@if($v)<span class="meta">{{ $v->label() }} · {{ $v->generated_at->format('j M Y') }}</span>@endif</p>
</li>
