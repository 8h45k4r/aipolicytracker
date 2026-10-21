@props(['policy'])
{{--
    The record's status badge with copyable snippets. The badge links to the
    permanent id address, so it keeps working if the page moves and carries the
    attribution CC BY asks for. Collapsed by default: most readers never need it.
--}}
@php($badge = route('badges.policy', $policy->slug))
@php($link = $policy->aipt_id ? route('record.id', $policy->aipt_id) : $policy->url())
@php($alt = config('aipolicytracker.site_name').': '.$policy->title.', '.app(\App\Services\PolicyData\RecordBadge::class)->value($policy))
<details {{ $attributes->merge(['class' => 'text-sm']) }} data-record-badge>
    <summary class="cursor-pointer text-brand-blue">Add a status badge to your site or README</summary>
    <p class="mt-2 text-brand-body">It shows the status and review state as recorded here and updates when the record does.</p>
    <p class="mt-2"><img src="{{ $badge }}" alt="{{ $alt }}" height="20" loading="lazy"></p>
    <p class="mt-2 label">Markdown</p>
    <pre class="mt-1 overflow-x-auto rounded-md bg-brand-paper p-3 text-xs" tabindex="0" role="region" aria-label="Badge in Markdown"><code>[![{{ $alt }}]({{ $badge }})]({{ $link }})</code></pre>
    <p class="mt-2 label">HTML</p>
    <pre class="mt-1 overflow-x-auto rounded-md bg-brand-paper p-3 text-xs" tabindex="0" role="region" aria-label="Badge in HTML"><code>{{ '<a href="'.$link.'"><img src="'.$badge.'" alt="'.e($alt).'" height="20"></a>' }}</code></pre>
</details>
