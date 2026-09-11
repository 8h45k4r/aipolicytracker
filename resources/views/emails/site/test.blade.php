@component('emails.site.layout', ['title' => 'Mail transport test'])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Transport test</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">Your email delivery works</h1>
<p style="margin:0 0 12px;">This message was sent from the admin settings page at {{ $sentAt->format('j F Y H:i') }} UTC via the <strong>{{ $transport }}</strong> transport. Subscribers will receive confirmation, weekly digest and alert emails in this layout.</p>
<p style="margin:0;"><a href="{{ route('backend.admin.settings') }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:600;">Open settings</a></p>
@endcomponent
