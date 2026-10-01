Join {{ $site }}

Hello {{ $name }}, {{ $inviter }} has created an account for you on {{ $site }}@if($role) with the {{ $role->label() }} role: {{ lcfirst($role->description()) }}@else.@endif

@if($note)
"{{ $note }}"

@endif
Choose your password: {{ $url }}

The link works for {{ $days }} days and only once.@if($role) Because the role gives admin access, you will be asked to set up an authenticator app when you first sign in.@endif

If you were not expecting this, ignore the email: nothing happens unless the link is used.
