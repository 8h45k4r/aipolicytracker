<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>{{ $title }}</title>
<style>
:root{color-scheme:light}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-size:14px;color:#1f2937;background:#fff;padding:12px 14px}
a{color:#006aac}h1{font-size:16px;margin:0 0 4px;color:#002147}p{margin:4px 0}.meta{color:#6b7280;font-size:12px}
ul{list-style:none;margin:8px 0 0;padding:0}li{padding:5px 0;border-top:1px solid #e5e7eb}.badge{display:inline-block;border-radius:3px;padding:1px 6px;font-size:11px;background:#f3f5f8;color:#1f2937}.b{background:#002147;color:#fff}
.attr{margin-top:10px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;padding-top:6px}.tiles{display:flex;flex-wrap:wrap;gap:3px;margin:4px 0 8px}.tile{display:inline-block;padding:2px 6px;border-radius:3px;font-size:11px;text-decoration:none;color:#1f2937;background:#f3f5f8}.tile.binding{background:#006aac;color:#fff}.tile.in_force{background:#002147;color:#fff}
</style></head><body>
{!! $body !!}
<p class="attr">Source: <a href="{{ $sourceUrl }}" target="_top">AIPolicyTracker</a>, records linked to official sources · <a href="{{ route('methodology') }}" target="_top">methodology</a> · not legal advice</p>
</body></html>
