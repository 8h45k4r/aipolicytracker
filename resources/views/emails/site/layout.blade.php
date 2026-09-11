<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'AI Policy Tracker' }}</title>
</head>
<body style="margin:0;padding:0;background:#F5F7FA;font-family:'IBM Plex Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;color:#1E2A3B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7FA;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #D8DEE8;">
<tr><td style="padding:20px 28px;border-bottom:1px solid #D8DEE8;">
<img src="{{ url('/brand/logo-on-light.svg') }}" alt="AIPolicyTracker" width="140" height="43" style="display:block;height:43px;width:auto;">
</td></tr>
<tr><td style="padding:24px 28px;font-size:15px;line-height:1.6;">
{!! $slot !!}
</td></tr>
<tr><td style="padding:16px 28px;border-top:1px solid #D8DEE8;font-size:12px;line-height:1.5;color:#5D6B7E;">
<p style="margin:0 0 6px;">Informational only, not legal advice. Every item links to its official source.</p>
<p style="margin:0;">You receive this because you subscribed at aipolicytracker.org. <a href="{{ $unsubscribeUrl ?? url('/') }}" style="color:#006AAC;">Unsubscribe</a> · <a href="{{ url('/') }}" style="color:#006AAC;">aipolicytracker.org</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
