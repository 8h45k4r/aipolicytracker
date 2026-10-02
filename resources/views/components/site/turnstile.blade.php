{{--
Cloudflare Turnstile, for any public form. Renders nothing until both keys are set
(Admin → Settings → Bot protection, or TURNSTILE_* in the environment).

The widget is not loaded with the page: public.js fetches Cloudflare's script the first
time someone starts filling the form in, so a page carrying the subscribe box does not
pay for it on every view. The server checks the token (App\Services\Security\Turnstile);
the widget alone proves nothing.
--}}
@php($turnstile = app(\App\Services\Security\Turnstile::class))
@if($turnstile->configured())
    <div {{ $attributes->merge(['class' => 'min-h-[65px]']) }} data-turnstile-slot data-sitekey="{{ $turnstile->siteKey() }}"></div>
    <noscript><p class="meta">This form runs a quick automated check that needs JavaScript.</p></noscript>
@endif
@error('turnstile')<p class="mt-1 text-sm text-state-bad" role="alert">{{ $message }}</p>@enderror
