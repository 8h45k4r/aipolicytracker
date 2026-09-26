{{-- One decision for every ticked submission. The form is a sibling of the list; the
     cards' checkboxes point at it by id. --}}
@props(['id' => 'bulk-submissions'])
<form method="post" action="{{ route('backend.review.decide.many') }}" id="{{ $id }}" class="mt-3 flex flex-wrap items-end gap-2 rounded-sm border border-brand-line bg-white p-3 text-sm">@csrf
    <p class="w-full flex flex-wrap items-center gap-2"><span class="font-medium text-brand-navy">Decide the selected submissions</span><span class="badge-neutral" data-bulk-count="{{ $id }}">0 selected</span><label class="ml-auto flex items-center gap-1 meta"><input type="checkbox" data-bulk-all="{{ $id }}" aria-label="Select every submission on this page"> select all on this page</label></p>
    <div><label for="{{ $id }}-decision" class="label !mb-0.5 !text-xs">Decision</label><select id="{{ $id }}-decision" name="decision" class="input !min-h-0 !py-1.5 !w-auto"><option value="approved">Approve</option><option value="needs_information">Needs information</option><option value="rejected">Reject</option></select></div>
    <div class="flex-1 min-w-[12rem]"><label for="{{ $id }}-notes" class="label !mb-0.5 !text-xs">Notes (internal, same for all)</label><input id="{{ $id }}-notes" name="notes" class="input !min-h-0 !py-1.5" maxlength="2000"></div>
    <div class="flex-1 min-w-[12rem]"><label for="{{ $id }}-public" class="label !mb-0.5 !text-xs">Public note (same for all)</label><input id="{{ $id }}-public" name="public_note" class="input !min-h-0 !py-1.5" maxlength="500" placeholder="Shown on /corrections"></div>
    <button type="submit" class="btn-primary !min-h-0 !py-1.5" data-bulk-needs="{{ $id }}" data-confirm="Record this decision for {n} submissions?">Record for selected</button>
</form>
