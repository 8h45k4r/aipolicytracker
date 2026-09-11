<x-auth-shell title="Confirm your password" eyebrow="Security check">
    <p class="mt-3 text-sm text-brand-body">This is a secure area of your account. Please confirm your password before continuing.</p>
    <form method="post" action="{{ route('password.confirm') }}" class="mt-5 space-y-4">@csrf
        <div><label for="password" class="label">Password</label><div class="relative"><input id="password" type="password" name="password" required autocomplete="current-password" class="input pr-20"><button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-brand-muted hover:text-brand-navy" data-password-toggle aria-controls="password" aria-pressed="false">Show</button></div></div>
        <button type="submit" class="btn-primary w-full">Confirm</button>
    </form>
</x-auth-shell>
