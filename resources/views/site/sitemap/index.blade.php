<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($entries as $e)
    <sitemap><loc>{{ $e['loc'] }}</loc>@if($e['lastmod'])<lastmod>{{ $e['lastmod'] }}</lastmod>@endif</sitemap>
@endforeach
</sitemapindex>
