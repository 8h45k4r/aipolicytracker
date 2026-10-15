@props(['revisions', 'since' => null])
{{--
    The record's own history, field by field (record_revisions, written by the import).
    Prose fields are marked "revised" rather than quoted; the repository has every wording.
--}}
<div {{ $attributes->merge(['class' => 'mt-3']) }} data-record-history>
    <h3 class="text-sm font-semibold text-brand-navy">Record history</h3>
    @if($revisions->isEmpty())
    <p class="mt-1 text-xs text-brand-muted">No change to this record's tracked fields has been recorded{{ $since ? ' since '.$since : '' }}.</p>
    @else
    <ol class="mt-2 space-y-2 border-l border-brand-line pl-4 text-sm">
        @foreach($revisions as $r)
        <li class="relative">
            <span aria-hidden="true" class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-brand-blue"></span>
            <p><time class="font-mono text-xs" datetime="{{ $r->changed_at->toDateString() }}">{{ $r->changed_at->format('j M Y') }}</time>@if($r->content_version) <span class="meta">· record version {{ $r->content_version }}</span>@endif</p>
            <ul class="mt-0.5 text-brand-body">
                @foreach($r->fields as $f)
                <li><span class="font-medium">{{ $f['label'] }}</span>: @if($f['to'] === 'revised' && $f['from'] === null)revised @else{{ $f['from'] ?? '—' }} → {{ $f['to'] ?? '—' }}@endif</li>
                @endforeach
            </ul>
            @if($r->change_summary)<p class="meta mt-0.5">{{ $r->change_summary }}</p>@endif
        </li>
        @endforeach
    </ol>
    @endif
</div>
