@props(['items'])
<nav aria-label="Breadcrumb" class="text-xs text-brand-muted">
    <ol class="flex flex-wrap items-center gap-1">
        @foreach($items as $i => [$name, $url])
            <li class="flex items-center gap-1">
                @if($loop->last)<span aria-current="page" class="text-brand-body">{{ $name }}</span>@else<a href="{{ $url }}" class="hover:text-brand-body">{{ $name }}</a><span aria-hidden="true">/</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
