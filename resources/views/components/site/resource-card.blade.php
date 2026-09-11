@props(['item'])
@php($types = config('resources.types'))
@php($fw = config('resources.frameworks'))
@php($url = $item['kind'] === 'tool' ? route('tools.show', $item['slug']) : route('guides.show', $item['slug']))
<li class="card-flat p-4 flex flex-col">
    <div class="flex flex-wrap gap-1.5">
        <span class="badge {{ $item['kind'] === 'tool' ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $types[$item['type']] ?? ucfirst($item['type']) }}</span>
        @if(!empty($item['files']))<span class="badge bg-state-goodbg text-state-good ring-state-good/30">Free download</span>@endif
        @foreach($item['frameworks'] as $f)<span class="badge-neutral">{{ $fw[$f] ?? $f }}</span>@endforeach
    </div>
    <a href="{{ $url }}" class="mt-3 text-lg font-semibold text-brand-navy hover:underline">{{ $item['title'] }}</a>
    <p class="mt-1 text-sm text-brand-body flex-1">{{ $item['short'] }}</p>
    <p class="mt-3 text-sm"><a href="{{ $url }}" class="font-medium text-brand-navy">{{ $item['kind'] === 'tool' ? 'Preview and download' : 'Read the guide' }} →</a></p>
</li>
