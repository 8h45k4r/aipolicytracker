@props(['title', 'description' => null, 'crumbs' => []])
{{-- The top of every admin page: where it sits, what it is, one line on what it is for, and
     its actions. The title matches the page's sidebar entry. A page below another passes
     :crumbs="[['Tool library', route(...)], ['Ivy']]" (the last, the page itself, unlinked),
     or fills the breadcrumb slot for anything else. --}}
<div class="flex flex-wrap items-end justify-between gap-4" {{ $attributes }}>
    <div class="min-w-0">
        @if(isset($breadcrumb))
        <nav class="adm-crumbs mb-1" aria-label="Breadcrumb">{{ $breadcrumb }}</nav>
        @elseif($crumbs !== [])
        <nav class="adm-crumbs mb-1" aria-label="Breadcrumb"><ol>
            @foreach($crumbs as $crumb)
            <li>@if(! $loop->last && ! empty($crumb[1]))<a href="{{ $crumb[1] }}">{{ $crumb[0] }}</a>@else<span{!! $loop->last ? ' aria-current="page"' : '' !!}>{{ $crumb[0] }}</span>@endif</li>
            @endforeach
        </ol></nav>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-brand-navy">{{ $title }}</h1>
        @if($description)<p class="mt-1 max-w-3xl text-sm text-brand-muted">{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2 text-sm">{{ $actions }}</div>@endisset
</div>
