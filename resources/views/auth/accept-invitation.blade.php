<x-auth-shell title="Accept your invitation" eyebrow="Invitation">
    <p class="mt-3 text-sm text-brand-body">Choose a password to finish setting up your account. If you were given an admin role, you will be asked to set up an authenticator app when you first sign in.</p>
    <form method="post" action="{{ route('invitation.store') }}" class="mt-5 space-y-4">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label for="email" class="label">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" class="input"></div>
        <div><label for="password" class="label">Password</label><div class="relative"><input id="password" type="password" name="password" required autocomplete="new-password" class="input pr-20" aria-describedby="password-hint"><button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-brand-muted hover:text-brand-navy" data-password-toggle aria-controls="password" aria-pressed="false">Show</button></div><p id="password-hint" class="mt-1 text-xs text-brand-muted">At least 8 characters with upper and lower case letters and a symbol.</p></div>
        <div><label for="password_confirmation" class="label">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input"></div>
        <button type="submit" class="btn-primary w-full">Set password and continue</button>
    </form>
</x-auth-shell>
