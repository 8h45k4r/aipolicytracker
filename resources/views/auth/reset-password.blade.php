<x-auth-shell title="Choose a new password" eyebrow="Password reset">
    <form method="post" action="{{ route('password.store') }}" class="mt-5 space-y-4">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label for="email" class="label">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" class="input"></div>
        <div><label for="password" class="label">New password</label><div class="relative"><input id="password" type="password" name="password" required autocomplete="new-password" class="input pr-20"><button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-brand-muted hover:text-brand-navy" data-password-toggle aria-controls="password" aria-pressed="false">Show</button></div></div>
        <div><label for="password_confirmation" class="label">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input"></div>
        <button type="submit" class="btn-primary w-full">Reset password</button>
    </form>
</x-auth-shell>
