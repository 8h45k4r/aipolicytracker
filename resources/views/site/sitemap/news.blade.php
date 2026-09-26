<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
@foreach($changes as $c)
    <url>
        <loc>{{ $c->url() }}</loc>
        <news:news>
            <news:publication><news:name>{{ config('aipolicytracker.site_name') }}</news:name><news:language>en</news:language></news:publication>
            <news:publication_date>{{ ($c->first_published_at ?? $c->occurred_on)->toAtomString() }}</news:publication_date>
            <news:title>{{ $c->jurisdiction?->name }}: {{ $c->title }}</news:title>
        </news:news>
    </url>
@endforeach
</urlset>
