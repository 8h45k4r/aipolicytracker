@extends('site.layouts.app')
@section('content')
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Which AI legal duties map to which standard</h1>
    <p class="mt-2 max-w-3xl text-brand-body">Organisations are audited against standards, but regulated by statutes. These crosswalks record, duty by duty, which clause of a standard corresponds to a legal requirement &mdash; so you can see which duties your existing evidence already reaches, and which it does not.</p>
    <p class="mt-2 max-w-3xl text-sm text-brand-muted">Every mapping is an editorial judgement recorded against one obligation and reviewable in the open data. Mappings cite clause numbers only and reproduce no standard text.</p>

    <section class="mt-8" aria-labelledby="frameworks-heading">
        <h2 id="frameworks-heading" class="section-title">Frameworks</h2>
        <ul class="mt-3 grid gap-4 md:grid-cols-2">
            @foreach($frameworks as $f)
            <li class="card-flat p-5 flex flex-col">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge {{ $f['certifiable'] ? 'bg-brand-navy text-white ring-brand-navy' : 'bg-brand-paper text-brand-body ring-brand-line' }}">{{ $f['certifiable'] ? 'Certifiable' : 'Voluntary' }}</span>
                    <span class="text-xs text-brand-muted">{{ $f['publisher'] }} &middot; {{ $f['published'] }}</span>
                </div>
                @if($f['obligations'] > 0)
                    <a href="{{ route('frameworks.show', $f['slug']) }}" class="mt-2 text-lg font-semibold text-brand-navy hover:underline">{{ $f['name'] }}</a>
                @else
                    <p class="mt-2 text-lg font-semibold text-brand-navy">{{ $f['name'] }}</p>
                @endif
                <p class="mt-1 text-sm text-brand-body flex-1">{{ $f['summary'] }}</p>
                @if($f['obligations'] > 0)
                    <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-md bg-brand-paper py-2"><dt class="text-xs text-brand-muted">Duties mapped</dt><dd class="text-xl font-semibold text-brand-navy">{{ $f['obligations'] }}</dd></div>
                        <div class="rounded-md bg-brand-paper py-2"><dt class="text-xs text-brand-muted">Jurisdictions</dt><dd class="text-xl font-semibold text-brand-navy">{{ $f['jurisdictions'] }}</dd></div>
                        <div class="rounded-md bg-brand-paper py-2"><dt class="text-xs text-brand-muted">Legally binding</dt><dd class="text-xl font-semibold text-brand-navy">{{ $f['binding'] }}</dd></div>
                    </dl>
                    <a href="{{ route('frameworks.show', $f['slug']) }}" class="mt-4 text-sm font-medium text-brand-blue hover:underline">See the {{ $f['obligations'] }} mapped duties &rarr;</a>
                @else
                    <p class="mt-4 text-sm text-brand-muted">No duties are crosswalked to this framework yet.</p>
                @endif
            </li>
            @endforeach
        </ul>
    </section>

    <section class="mt-10" aria-labelledby="crosswalk-heading">
        <h2 id="crosswalk-heading" class="section-title">Law-to-standard crosswalks</h2>
        <p class="mt-1 text-sm text-brand-body">Each row is one jurisdiction's AI duties mapped to one standard. The count is how many duties carry a mapping, not how many exist.</p>
        <div class="table-wrap mt-3">
            <table>
                <caption class="sr-only">Available law-to-standard crosswalks</caption>
                <thead><tr><th scope="col">Jurisdiction</th><th scope="col">Framework</th><th scope="col">Duties mapped</th><th scope="col">Instruments</th></tr></thead>
                <tbody>
                @foreach($covered as $f)
                    @foreach($crosswalkRows[$f['key']] as $row)
                    <tr>
                        <td><a href="{{ route('frameworks.crosswalk', [$f['slug'], $row['jurisdiction']->slug]) }}" class="font-medium text-brand-navy hover:underline">{{ $row['jurisdiction']->name }}</a></td>
                        <td class="whitespace-nowrap">{{ $f['short'] }}</td>
                        <td>{{ $row['rows'] }}</td>
                        <td>{{ $row['instruments'] }}</td>
                    </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <x-site.disclaimer class="mt-8" />
</div>
@endsection
