@component('emails.site.layout', ['title' => 'Your invitation', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Invitation</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">Join {{ $site }}</h1>
<p style="margin:0 0 12px;">Hello {{ $name }}, {{ $inviter }} has created an account for you on {{ $site }}@if($role) with the <strong>{{ $role->label() }}</strong> role: {{ lcfirst($role->description()) }}@else.@endif</p>
@if($note)<p style="margin:0 0 12px;padding:10px 12px;border-left:3px solid #006AAC;background:#F4F7FA;">{{ $note }}</p>@endif
<p style="margin:0 0 16px;"><a href="{{ $url }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:10px 18px;font-weight:600;">Choose your password</a></p>
<p style="margin:0 0 8px;font-size:13px;color:#5D6B7E;">The link works for {{ $days }} days and only once.@if($role) Because the role gives admin access, you will be asked to set up an authenticator app when you first sign in.@endif</p>
<p style="margin:0;font-size:13px;color:#5D6B7E;">If you were not expecting this, ignore the email: nothing happens unless the link is used.</p>
@endcomponent
