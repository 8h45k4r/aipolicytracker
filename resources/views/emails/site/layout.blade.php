<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="x-apple-disable-message-reformatting">
<title>{{ $title ?? config('aipolicytracker.site_name') }}</title>
</head>
<body style="margin:0;padding:0;background:#F5F7FA;font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;color:#1E2A3B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7FA;padding:32px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #D8DEE8;border-radius:6px;">
<tr><td align="center" style="padding:32px 28px 8px;">
<a href="{{ url('/') }}" style="text-decoration:none;"><img src="{{ url('/brand/logo-on-light.png') }}" alt="{{ config('aipolicytracker.site_name') }}" width="180" style="display:block;width:180px;max-width:100%;height:auto;margin:0 auto;"></a>
<p style="margin:16px 0 2px;font-size:16px;font-weight:700;color:#002147;">{{ config('aipolicytracker.tagline') }}</p>
<p style="margin:0;font-size:14px;color:#5D6B7E;">{{ config('aipolicytracker.positioning') }}</p>
</td></tr>
<tr><td style="padding:24px 36px 8px;font-size:15px;line-height:1.6;">
{!! $slot !!}
</td></tr>
<tr><td style="padding:8px 36px 0;"><hr style="border:0;border-top:1px solid #D8DEE8;margin:16px 0 0;"></td></tr>
<tr><td align="center" style="padding:24px 28px 8px;">
<p style="margin:0 0 12px;font-size:15px;font-weight:700;color:#1E2A3B;">Follow us on social:</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr>
@foreach(config('aipolicytracker.social', []) as $social)
<td style="padding:0 8px;"><a href="{{ $social['url'] }}" style="text-decoration:none;"><img src="{{ url('/brand/social/'.$social['key'].'.png') }}" alt="{{ $social['label'] }}" width="40" height="40" style="display:block;width:40px;height:40px;border-radius:8px;"></a></td>
@endforeach
</tr></table>
</td></tr>
<tr><td style="padding:8px 36px 0;"><hr style="border:0;border-top:1px solid #D8DEE8;margin:16px 0 0;"></td></tr>
<tr><td align="center" style="padding:20px 28px 28px;font-size:12px;line-height:1.6;color:#5D6B7E;">
<p style="margin:0 0 6px;">© {{ date('Y') }} {{ config('aipolicytracker.site_name') }} · {{ config('aipolicytracker.organization.name') }}. Informational only, not legal advice; every item links to its official source.</p>
<p style="margin:0;">@if(!empty($unsubscribeUrl))You receive this because you subscribed at aipolicytracker.org. <a href="{{ $unsubscribeUrl }}" style="color:#006AAC;">Unsubscribe</a> · @endif<a href="{{ url('/') }}" style="color:#006AAC;">aipolicytracker.org</a> · <a href="{{ config('aipolicytracker.organization.url') }}" style="color:#006AAC;">certifyi.ai</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
