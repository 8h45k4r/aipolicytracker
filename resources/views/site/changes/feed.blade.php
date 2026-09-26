<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ $title ?? 'AIPolicyTracker: AI policy changes' }}</title>
    <link>{{ $link ?? route('changes.index') }}</link>
    <description>{{ $description ?? 'Dated, source-backed AI policy changes across jurisdictions. Informational only; not legal advice.' }}</description>
    <language>en</language>
    <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
    <atom:link href="{{ $self ?? route('changes.feed') }}" rel="self" type="application/rss+xml" />
@foreach($changes as $c)
    <item>
        <title>{{ $c->jurisdiction->name }}: {{ $c->title }}</title>
        <link>{{ $c->url() }}</link>
        <guid isPermaLink="true">{{ $c->url() }}</guid>
        <pubDate>{{ $c->occurred_on->copy()->startOfDay()->toRssString() }}</pubDate>
        <category>{{ $c->impact_level }}</category>
        <description>{{ $c->what_changed }}@if($c->practical_impact) Practical impact: {{ $c->practical_impact }}@endif Official source: {{ $c->official_source_url }}. {{ $c->verificationLabel() }}.</description>
    </item>
@endforeach
</channel>
</rss>
