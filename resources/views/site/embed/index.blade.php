@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Embed AI regulation on your site</h1>
    <p class="mt-2 text-brand-body">Three live widgets, server-rendered from the records with a followed attribution link. No script is required: the snippet is a plain iframe. Only pages under <code>/embed/</code> may be framed.</p>
    <form method="get" action="{{ route('embed.index') }}" class="mt-6 card-flat p-4 grid gap-3 sm:grid-cols-3 items-end" data-autosubmit aria-label="Choose a widget">
        <div><label for="e-kind" class="label">Widget</label><select id="e-kind" name="kind" class="input">@foreach(\App\Http\Controllers\Site\EmbedController::KINDS as $k => $label)<option value="{{ $k }}" @selected($kind === $k)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="e-j" class="label">Jurisdiction (card and deadlines)</label><select id="e-j" name="jurisdiction" class="input">@foreach($jurisdictions as $j)<option value="{{ $j->slug }}" @selected($jurisdiction === $j->slug)>{{ $j->name }}</option>@endforeach</select></div>
        <button type="submit" class="btn-primary">Update</button>
    </form>
    <section class="mt-6" aria-labelledby="prev-heading"><h2 id="prev-heading" class="section-title">Preview</h2>
        <iframe src="{{ $src }}" title="{{ \App\Http\Controllers\Site\EmbedController::KINDS[$kind] }} from AIPolicyTracker" width="100%" height="{{ $height }}" loading="lazy" class="mt-3 max-w-[720px] rounded-sm border border-brand-line"></iframe>
    </section>
    <section class="mt-6" aria-labelledby="code-heading"><h2 id="code-heading" class="section-title">Snippet</h2>
        <pre class="mt-3 overflow-x-auto rounded-sm bg-brand-paper p-3 text-xs"><code>{{ $snippet }}</code></pre>
        <p class="mt-2 text-sm text-brand-body">Optional: add <code>&lt;script src="{{ url('/embed.js') }}" async&gt;&lt;/script&gt;</code> once and the frame sizes itself to its content (about 1 KB, no cookies, no tracking).</p>
    </section>
    <section class="mt-6 text-sm text-brand-body" aria-labelledby="terms-heading"><h2 id="terms-heading" class="section-title">Terms</h2><p class="mt-2">Widgets are free under CC BY 4.0 for the data they show; keep the attribution link. They refresh with the records, carry no cookies or analytics, and show nothing that is not on the public site. Informational only; not legal advice.</p></section>
</div>
@endsection
