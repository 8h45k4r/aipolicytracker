@props(['address'])
{{-- The edge rewrites any address it finds into a placeholder that a script decodes,
     and the content security policy refuses that script, so readers saw
     "[email protected]". These markers tell it to leave the address as written. --}}
<!--email_off--><a href="mailto:{{ $address }}" {{ $attributes }}>{{ $address }}</a><!--/email_off-->
