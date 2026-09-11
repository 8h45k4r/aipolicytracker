<x-auth-shell title="Reset your password" eyebrow="Password reset">
    <p class="mt-3 text-sm text-brand-body">Enter the email address on your account and we will send a link to choose a new password.</p>
    <form method="post" action="{{ route('password.email') }}" class="mt-5 space-y-4">@csrf
        <div><label for="email" class="label">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input"></div>
        <button type="submit" class="btn-primary w-full">Email password reset link</button>
    </form>
</x-auth-shell>
