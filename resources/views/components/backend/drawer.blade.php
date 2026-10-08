@props(['id', 'title', 'description' => null, 'open' => false])
{{-- A side panel for a create or edit form. The page keeps a plain link or button with
     data-drawer-open set to this id; without JavaScript the form is reached at its
     own address. Pass :open="true" when the form came back with errors, so it reopens. --}}
<dialog id="{{ $id }}" class="adm-drawer" aria-labelledby="{{ $id }}-title" @if($open) data-open-on-load @endif {{ $attributes }}>
    <div class="adm-drawer-head">
        <div class="min-w-0">
            <h2 id="{{ $id }}-title" class="text-lg font-semibold text-brand-navy">{{ $title }}</h2>
            @if($description)<p class="mt-0.5 text-sm text-brand-muted">{{ $description }}</p>@endif
        </div>
        <button type="button" class="btn-secondary !min-h-[34px] !px-2.5" data-drawer-close aria-label="Close">×</button>
    </div>
    <div class="adm-drawer-body">{{ $slot }}</div>
    @isset($footer)<div class="adm-drawer-foot">{{ $footer }}</div>@endisset
</dialog>
