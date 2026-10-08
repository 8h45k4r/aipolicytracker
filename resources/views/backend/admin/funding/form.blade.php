{{-- The add or edit form for one funder, in a side panel or (without JavaScript) on the
     page. $form names it; after a refused save only the form that was sent shows the
     typed values and the errors, since every form on the page shares one error bag. --}}
@php
    $f = $funder ?? new \App\Models\Funder(['kind' => 'grant']);
    $editing = $f->exists;
    $mine = old('_drawer') === $form;
    $val = fn (string $field, $current) => $mine ? old($field) : $current;
    $err = fn (string $field) => $mine && $errors->has($field) ? $errors->first($field) : null;
    $p = $form.'-';
@endphp
<form method="post" action="{{ $editing ? route('backend.admin.funding.update', $f) : route('backend.admin.funding.store') }}" id="{{ $form }}-form" class="space-y-4" data-funder-form="{{ $form }}">@csrf @if($editing) @method('PUT') @endif
    <input type="hidden" name="_drawer" value="{{ $form }}">
    @if($mine && $errors->any())<p class="rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-sm text-state-bad" role="alert">Not saved. Fix the fields marked below.</p>@endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label for="{{ $p }}name" class="label">Name</label><input id="{{ $p }}name" name="name" class="input" required maxlength="160" value="{{ $val('name', $f->name) }}" @if($err('name')) aria-invalid="true" @endif>@if($err('name'))<p class="mt-1 text-xs text-state-bad">{{ $err('name') }}</p>@endif</div>
        <div><label for="{{ $p }}kind" class="label">Kind</label><select id="{{ $p }}kind" name="kind" class="input">@foreach(\App\Models\Funder::KINDS as $k => $l)<option value="{{ $k }}" @selected($val('kind', $f->kind) === $k)>{{ $l }}</option>@endforeach</select>@if($err('kind'))<p class="mt-1 text-xs text-state-bad">{{ $err('kind') }}</p>@endif</div>
        <div><label for="{{ $p }}amount" class="label">Amount</label><input id="{{ $p }}amount" name="amount_display" class="input" required maxlength="64" value="{{ $val('amount_display', $f->amount_display) }}" placeholder="As agreed, with currency" @if($err('amount_display')) aria-invalid="true" @endif><p class="meta mt-1">Free text, shown as written.</p>@if($err('amount_display'))<p class="mt-1 text-xs text-state-bad">{{ $err('amount_display') }}</p>@endif</div>
        <div><label for="{{ $p }}period" class="label">Period</label><input id="{{ $p }}period" name="period" class="input" maxlength="64" value="{{ $val('period', $f->period) }}" placeholder="e.g. a year, 2027"></div>
        <div class="sm:col-span-2"><label for="{{ $p }}purpose" class="label">What it pays for</label><textarea id="{{ $p }}purpose" name="purpose" class="input" rows="3" required maxlength="2000" @if($err('purpose')) aria-invalid="true" @endif>{{ $val('purpose', $f->purpose) }}</textarea>@if($err('purpose'))<p class="mt-1 text-xs text-state-bad">{{ $err('purpose') }}</p>@endif</div>
        <div class="sm:col-span-2"><label for="{{ $p }}url" class="label">Link (optional)</label><input id="{{ $p }}url" name="url" type="url" class="input" maxlength="512" value="{{ $val('url', $f->url) }}" placeholder="https://" @if($err('url')) aria-invalid="true" @endif><p class="meta mt-1">A full https:// address.</p>@if($err('url'))<p class="mt-1 text-xs text-state-bad" data-field-error="url">{{ $err('url') }}</p>@endif</div>
        <div><label for="{{ $p }}starts" class="label">Starts on (optional)</label><input id="{{ $p }}starts" name="starts_on" type="date" class="input" value="{{ $val('starts_on', $f->starts_on?->format('Y-m-d')) }}"></div>
        <div><label for="{{ $p }}ends" class="label">Ends on (optional)</label><input id="{{ $p }}ends" name="ends_on" type="date" class="input" value="{{ $val('ends_on', $f->ends_on?->format('Y-m-d')) }}" @if($err('ends_on')) aria-invalid="true" @endif>@if($err('ends_on'))<p class="mt-1 text-xs text-state-bad">{{ $err('ends_on') }}</p>@endif</div>
        <div><label for="{{ $p }}sort" class="label">Order (optional)</label><input id="{{ $p }}sort" name="sort" type="number" min="0" class="input" value="{{ $val('sort', $editing ? $f->sort : null) }}"><p class="meta mt-1">Empty puts a new entry last.</p></div>
        <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="published" value="0"><input type="checkbox" name="published" value="1" @checked($val('published', $f->published))> Published on /funding</label></div>
    </div>
    @if($inline ?? false)
    <div class="flex gap-2"><button type="submit" class="btn-primary">{{ $editing ? 'Save funder' : 'Add funder' }}</button><a href="{{ route('backend.admin.funding.index') }}" class="btn-secondary">Cancel</a></div>
    @endif
</form>
