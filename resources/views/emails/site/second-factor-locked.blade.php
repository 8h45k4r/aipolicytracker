@component('emails.site.layout', ['title' => 'Second-factor sign-in locked'])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Account security</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">Authenticator codes are locked on your admin account</h1>
<p style="margin:0 0 12px;">{{ $failures }} wrong authenticator codes were entered for your account, so codes from your authenticator app are refused for the next {{ $hours }} hours (from {{ $lockedAt->format('j F Y H:i') }} UTC). A one-time recovery code still works.</p>
<p style="margin:0 0 12px;">The second-factor step only appears after the correct password. If this was not you, change your password now and tell the site owner.</p>
<p style="margin:0;"><a href="{{ route('password.request') }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:600;">Change your password</a></p>
@endcomponent
