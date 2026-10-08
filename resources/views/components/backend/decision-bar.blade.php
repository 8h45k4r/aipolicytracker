{{-- One decision for the selected submissions: the ticked cards, or every submission the
     list's filters match. The form is a sibling of the list; the cards' checkboxes point at it
     by id. It posts to a URL that carries the list's query string, so "all matching" means
     exactly what the page shows. On a phone it is a compact bar along the bottom that appears
     once a card is ticked (admin.css, "Bulk bars"). --}}
@props(['id' => 'bulk-submissions', 'matching' => 0, 'onPage' => 0, 'limit' => 1000])
@php($mine = old('_form') === $id)
@php($wholeFilter = $mine && old('scope') === 'filtered')
@php($picked = $mine ? count((array) old('ids', [])) : 0)
<form method="post" action="{{ route('backend.review.decide.many', request()->except(['page'])) }}" id="{{ $id }}" class="adm-bulkbar sticky top-0 z-10 mt-3 flex flex-wrap items-end gap-2 rounded-sm border border-brand-line bg-white p-3 text-sm shadow-sm">@csrf
    <input type="hidden" name="_form" value="{{ $id }}">
    <p class="w-full flex flex-wrap items-center gap-x-4 gap-y-1">
        <span class="font-medium text-brand-navy">Decide the selected submissions</span>
        <span class="badge-neutral" data-bulk-count="{{ $id }}" data-bulk-count-all="all {{ $matching }} matching">{{ $wholeFilter ? 'all '.$matching.' matching' : $picked.' selected' }}</span>
        @if($matching > $onPage)<label class="flex items-center gap-1 meta"><input type="checkbox" name="scope" value="filtered" data-bulk-scope="{{ $id }}" data-bulk-scope-count="{{ $matching }}" @checked($wholeFilter)> apply to all {{ $matching }} matching the filters, not only this page{{ $matching > $limit ? ' (up to '.number_format($limit).' per action)' : '' }}</label>@endif
    </p>
    <div><label for="{{ $id }}-decision" class="label !mb-0.5 !text-xs">Decision</label><select id="{{ $id }}-decision" name="decision" class="input !min-h-0 !py-1.5 !w-auto" required><option value="">Choose…</option>@foreach(['approved' => 'Approve', 'needs_information' => 'Needs information', 'rejected' => 'Reject'] as $value => $label)<option value="{{ $value }}" @selected($mine && old('decision') === $value)>{{ $label }}</option>@endforeach</select></div>
    <div class="flex-1 min-w-[12rem]"><label for="{{ $id }}-notes" class="label !mb-0.5 !text-xs">Notes (internal, same for all)</label><input id="{{ $id }}-notes" name="notes" value="{{ $mine ? old('notes') : '' }}" class="input !min-h-0 !py-1.5" maxlength="2000"></div>
    <div class="flex-1 min-w-[12rem]"><label for="{{ $id }}-public" class="label !mb-0.5 !text-xs">Public note (same for all)</label><input id="{{ $id }}-public" name="public_note" value="{{ $mine ? old('public_note') : '' }}" class="input !min-h-0 !py-1.5" maxlength="500" placeholder="Shown on /corrections"></div>
    <button type="submit" class="btn-primary btn-sm" data-bulk-needs="{{ $id }}" data-confirm="Record this decision for {n} submissions? It is published to the corrections log.">Record for selected</button>
</form>
