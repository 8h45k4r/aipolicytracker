<x-auth-shell title="Verify your email address" eyebrow="Account">
    <p class="mt-3 text-sm text-brand-body">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Open it to confirm your address. You can keep using the site meanwhile; verification protects your account and download history.</p>
    <form method="post" action="{{ route('verification.send') }}" class="mt-5">@csrf<button type="submit" class="btn-primary w-full">Resend verification email</button></form>
    <form method="post" action="{{ route('logout') }}" class="mt-3">@csrf<button type="submit" class="btn-secondary w-full">Sign out</button></form>
</x-auth-shell>
