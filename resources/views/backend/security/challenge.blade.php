<x-auth-shell title="Enter your authenticator code" eyebrow="Admin security" panel-title="Second factor" panel-text="Every admin session proves a code from your authenticator before it can publish, decide or read keys. A remembered browser is not enough on its own.">
    <form method="post" action="{{ route('admin.two-factor.verify') }}" class="mt-5 space-y-4">@csrf
        <div><label for="code" class="label">6-digit code, or a recovery code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus class="input font-mono tracking-widest" maxlength="20">@error('code')<p class="mt-1 text-xs text-state-bad" role="alert">{{ $message }}</p>@enderror</div>
        <button type="submit" class="btn-primary w-full">Continue</button>
    </form>
    <p class="mt-4 text-xs text-brand-muted">Five attempts a minute. Lost the phone and the codes? An operator with shell access can run <code>php artisan admin:two-factor-reset</code> for your address.</p>
</x-auth-shell>
