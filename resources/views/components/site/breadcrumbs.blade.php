@props(['items'])
<nav aria-label="Breadcrumb" class="text-xs text-slate-500">
    <ol class="flex flex-wrap items-center gap-1">
        @foreach($items as $i => [$name, $url])
            <li class="flex items-center gap-1">
                @if($loop->last)<span aria-current="page" class="text-slate-700">{{ $name }}</span>@else<a href="{{ $url }}" class="hover:text-slate-800">{{ $name }}</a><span aria-hidden="true">/</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
