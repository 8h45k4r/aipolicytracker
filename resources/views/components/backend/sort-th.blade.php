@props(['key', 'label', 'filters', 'class' => ''])
{{-- A column header that sorts the list by itself; a second click reverses the order. --}}
@php($active = $filters->sort === $key)
@php($dir = $active && $filters->dir === 'desc' ? 'asc' : 'desc')
<th scope="col" class="{{ $class }}" @if($active) aria-sort="{{ $filters->dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $dir, 'page' => null]) }}" class="inline-flex items-center gap-1 no-underline text-inherit hover:text-brand-navy">{{ $label }}<span aria-hidden="true" class="text-[10px] {{ $active ? 'text-brand-navy' : 'text-brand-line' }}">{{ $active && $filters->dir === 'asc' ? '▲' : '▼' }}</span></a>
</th>
