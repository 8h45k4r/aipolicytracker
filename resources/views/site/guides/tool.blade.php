@extends('site.layouts.app')
@section('content')
@php($fw = config('resources.frameworks'))
<div class="container-site py-8">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <header class="mt-3">
        <div class="flex flex-wrap gap-1.5"><span class="badge bg-brand-navy text-white ring-brand-navy">{{ (\App\Models\Tool::TYPES[$tool->type] ?? ucfirst($tool->type)) }}</span><span class="badge bg-state-goodbg text-state-good ring-state-good/30">Free download</span>@foreach(($tool->frameworks ?? []) as $f)<span class="badge-neutral">{{ $fw[$f] ?? $f }}</span>@endforeach</div>
        <h1 class="mt-3 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Free {{ $tool->title }}</h1>
        <p class="mt-3 max-w-[64ch] text-brand-body leading-7">{{ $tool->short }}</p>
        <div class="mt-4 flex flex-wrap gap-2"><a href="#preview" class="btn-secondary">Preview {{ strtolower((\App\Models\Tool::TYPES[$tool->type] ?? ucfirst($tool->type))) }}</a>
            @auth<a href="#download" class="btn-primary">Download free {{ strtolower((\App\Models\Tool::TYPES[$tool->type] ?? ucfirst($tool->type))) }}</a>@else<a href="{{ route('tools.gate', $tool->slug) }}" class="btn-primary" data-track="download_click" data-track-label="{{ $tool->slug }}">Download free {{ strtolower((\App\Models\Tool::TYPES[$tool->type] ?? ucfirst($tool->type))) }}</a>@endauth</div>
        <p class="mt-2 meta">Formats: {{ $formats }} · Version {{ $tool->version }} · Last updated {{ ($tool->updated_on?->format('j M Y') ?? '—') }}</p>
    </header>
    <div class="mt-8 grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2 min-w-0">
            <section aria-labelledby="purpose-heading"><h2 id="purpose-heading" class="section-title">What it is for</h2><p class="prose-policy mt-2">{{ $tool->purpose }}</p></section>
            <section id="preview" class="mt-8" aria-labelledby="preview-heading">
                <h2 id="preview-heading" class="section-title">Preview: fields in the {{ strtolower((\App\Models\Tool::TYPES[$tool->type] ?? ucfirst($tool->type))) }}</h2>
                <div class="table-wrap mt-3"><table><caption class="sr-only">Fields in {{ $tool->title }}</caption><thead><tr><th scope="col">Field</th><th scope="col">What to record</th></tr></thead><tbody>@foreach(($tool->fields ?? []) as [$name, $desc])<tr><th scope="row" class="font-medium whitespace-nowrap">{{ $name }}</th><td>{{ $desc }}</td></tr>@endforeach</tbody></table></div>
            </section>
            <section class="mt-8" aria-labelledby="how-heading"><h2 id="how-heading" class="section-title">How to use it</h2><ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-brand-body">@foreach(($tool->instructions ?? []) as $step)<li>{{ $step }}</li>@endforeach</ol></section>
            @if($policies->isNotEmpty() || $guides->isNotEmpty())
            <section class="mt-8" aria-labelledby="map-heading"><h2 id="map-heading" class="section-title">Framework and policy mapping</h2>
                <ul class="mt-2 space-y-1.5 text-sm">@foreach($policies as $p)<li><a href="{{ $p->url() }}" class="text-brand-navy">{{ $p->short_title ?: $p->title }}</a> <span class="meta">{{ $p->jurisdiction->name }} · {{ $p->statusEnum()->label() }}</span></li>@endforeach
                @foreach($guides as $g)<li><a href="{{ route('guides.show', $g['slug']) }}" class="text-brand-navy">{{ $g['h1'] }}</a> <span class="meta">guide</span></li>@endforeach</ul>
            </section>
            @endif
            <section id="download" class="mt-10 card-flat p-5" aria-labelledby="dl-heading">
                @auth
                <h2 id="dl-heading" class="section-title">Download free {{ strtolower((\App\Models\Tool::TYPES[$tool->type] ?? ucfirst($tool->type))) }}</h2>
                <form method="post" action="{{ route('tools.download', $tool->slug) }}" class="mt-3 space-y-3 text-sm">@csrf
                    <label class="flex items-start gap-2"><input type="checkbox" name="terms" value="1" required class="mt-1 rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy"><span>I accept the template licence: {{ config('resources.license') }}</span></label>
                    @error('terms')<p class="text-xs text-state-bad">{{ $message }}</p>@enderror
                    <label class="flex items-start gap-2"><input type="checkbox" name="updates" value="1" class="mt-1 rounded-sm border-brand-line text-brand-navy focus:ring-brand-navy" @checked(auth()->user()->marketing_consent_at)><span>Email me when a related AI policy requirement changes (optional; unsubscribe any time).</span></label>
                    <button type="submit" class="btn-primary" data-track="download_submit" data-track-label="{{ $tool->slug }}">Get the files ({{ $formats }})</button>
                    @if($previousDownload)<p class="meta">You downloaded version {{ $previousDownload->version }} on {{ $previousDownload->created_at->format('j M Y') }}. <a href="{{ route('tools.ready', [$tool->slug, $previousDownload]) }}">Get the links again</a></p>@endif
                </form>
                @else
                <h2 id="dl-heading" class="section-title">Create a free account to download</h2>
                <p class="mt-2 text-sm text-brand-body">Get instant access to this free {{ strtolower((\App\Models\Tool::TYPES[$tool->type] ?? ucfirst($tool->type))) }} and receive updates when related AI policy requirements change.</p>
                <div class="mt-3 flex flex-wrap gap-2"><a href="{{ route('tools.gate', $tool->slug) }}" class="btn-primary">Continue with email</a><a href="{{ route('login') }}" class="btn-secondary">I already have an account</a></div>
                <p class="mt-3 text-xs text-brand-muted">By downloading, you agree to the template licence and acknowledge that AIPolicyTracker provides informational resources, not legal advice.</p>
                @endauth
            </section>
            <x-site.faq :items="array_map(fn ($q) => ['question' => $q[0], 'answer' => $q[1]], $faq)" />
            <x-site.disclaimer class="mt-8" />
        </div>
        <aside class="space-y-6">
            <div class="lg:sticky lg:top-4 space-y-6">
                <div class="card-flat p-4 text-sm"><p class="font-semibold text-brand-navy">At a glance</p>
                    <dl class="mt-2 space-y-1.5 text-brand-body">
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Type</dt><dd>{{ (\App\Models\Tool::TYPES[$tool->type] ?? ucfirst($tool->type)) }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Formats</dt><dd class="text-right">{{ $formats }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Version</dt><dd>{{ $tool->version }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Updated</dt><dd>{{ ($tool->updated_on?->format('j M Y') ?? '—') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Licence</dt><dd>CC BY 4.0</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-brand-muted">Topics</dt><dd class="text-right">{{ implode(', ', array_map(fn ($t) => config('resources.topics')[$t] ?? $t, ($tool->topics ?? []))) }}</dd></div>
                    </dl></div>
                <div class="flex flex-col gap-2 text-sm"><x-site.save-button type="tool" :slug="$tool->slug" :title="$tool->title" :url="$tool->url()" meta="Free tool" /><button type="button" class="btn-secondary" data-copy-link>Copy link</button></div>
                @if($next)<div class="text-sm"><p class="font-semibold text-brand-navy">Next step</p><p class="mt-1"><a href="{{ $next->url() }}" class="text-brand-navy">Download the {{ $next->title }} →</a></p></div>@endif
                <x-site.certifyi-cta label="Turn this template into a live governance workflow" />
            </div>
        </aside>
    </div>
</div>
@endsection
