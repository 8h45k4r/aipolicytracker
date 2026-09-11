<div class="table-wrap table-sticky mt-4">
    <table>
        <caption class="sr-only">Comparison of {{ $jurisdictions->pluck('name')->implode(', ') }}</caption>
        <thead><tr><th scope="col" class="min-w-[10rem]">Category</th>@foreach($jurisdictions as $j)<th scope="col" class="min-w-[16rem]"><a href="{{ $j->url() }}" class="text-slate-900 hover:underline">{{ $j->name }}</a><div class="text-xs font-normal text-slate-500"><x-site.verified :record="$j" /></div></th>@endforeach</tr></thead>
        <tbody>
        @foreach($rows as $key => $row)
        <tr>
            <th scope="row" class="font-medium">{{ $row['label'] }}</th>
            @foreach($jurisdictions as $j)
            @php($cell = $row['cells'][$j->slug] ?? ['text' => '—', 'links' => []])
            <td>
                @if($cell['text'])<p class="text-slate-700">{{ $cell['text'] }}</p>@endif
                @if(!empty($cell['links']))<ul class="mt-1 space-y-1">@foreach($cell['links'] as $l)<li><a href="{{ $l['url'] }}" class="text-teal-800 hover:underline" @if(!empty($l['external'])) rel="noopener" data-track="source_click" @endif>{{ $l['name'] }}</a></li>@endforeach</ul>@endif
            </td>
            @endforeach
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="mt-2 text-xs text-slate-500">Cells are generated from published records; a category showing "not recorded" means no source-backed entry exists yet, not that the jurisdiction has no rules. Scroll horizontally on small screens.</p>
