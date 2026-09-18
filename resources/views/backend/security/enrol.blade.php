<x-auth-shell title="Set up your authenticator" eyebrow="Admin security" panel-title="A password alone no longer opens the admin" panel-text="The admin publishes records, decides submissions and holds API keys. From now on every admin session must also prove a code from an authenticator app on your phone.">
    <p class="mt-3 text-sm text-brand-body">Open an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password or similar) and add a new account with this key. The key is shown here only; it is not sent to any other service to be drawn as a picture.</p>
    <div class="mt-4 card-flat p-4">
        <p class="label">Setup key</p>
        <p class="mt-1 font-mono text-lg tracking-wider text-brand-navy select-all break-all">{{ $secret }}</p>
        <p class="mt-2 text-xs text-brand-muted">Time-based, 6 digits, 30 seconds. On a phone, <a href="{{ $uri }}" class="text-brand-blue">open this link</a> to add it in one tap.</p>
    </div>
    <form method="post" action="{{ route('admin.two-factor.confirm') }}" class="mt-5 space-y-4">@csrf
        <div><label for="code" class="label">Enter the 6-digit code the app shows now</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" required autofocus class="input font-mono tracking-widest" maxlength="7">@error('code')<p class="mt-1 text-xs text-state-bad" role="alert">{{ $message }}</p>@enderror</div>
        <button type="submit" class="btn-primary w-full">Confirm and continue</button>
    </form>
    <p class="mt-4 text-xs text-brand-muted">You will be given recovery codes on the next screen. Keep them somewhere safe: they are the only way in if you lose the phone.</p>
</x-auth-shell>
