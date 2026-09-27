<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>AIPolicyTracker: template versions</title>
    <link>{{ route('templates.index') }}</link>
    <description>New versions of the AI governance templates generated from the records on aipolicytracker.org, with what changed. CC BY 4.0. Informational only; not legal advice.</description>
    <language>en</language>
    <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
    <atom:link href="{{ route('templates.feed') }}" rel="self" type="application/rss+xml" />
@foreach($versions as $v)
@php($meta = \App\Services\Templates\TemplateCatalog::find($v->slug))
@continue(!$meta)
    <item>
        <title>{{ $meta['title'] }} {{ $v->label() }}</title>
        <link>{{ $v->url() }}</link>
        <guid isPermaLink="false">{{ $v->slug }}-v{{ $v->version }}</guid>
        <pubDate>{{ $v->generated_at->toRssString() }}</pubDate>
        <category>{{ $meta['type'] }}</category>
        <description>{{ $v->changelog }} Built from dataset {{ $v->dataset_version }}. Formats: {{ implode(', ', array_map('strtoupper', $v->formats())) }}.</description>
    </item>
@endforeach
</channel>
</rss>
