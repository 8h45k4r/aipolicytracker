@extends('site.layouts.app')
@section('content')
<div class="container-site py-12 max-w-xl">
    <h1 class="text-2xl font-semibold tracking-tight text-brand-navy">Stop daily alert emails</h1>
    @if($done)
    <p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Done. No more alert emails will be sent to {{ $user->email }}. Your watches are kept; turn emails back on any time from <a href="{{ route('following.index') }}">your alerts page</a>.</p>
    @else
    <p class="mt-3 text-brand-body">This turns off alert emails for <span class="font-medium">{{ $user->email }}</span>. Your watches, other channels and account stay as they are. No sign-in needed.</p>
    <form method="post" action="{{ request()->fullUrl() }}" class="mt-5">@csrf<button type="submit" class="btn-primary">Stop alert emails</button> <a href="{{ route('home') }}" class="btn-secondary">Keep them</a></form>
    @endif
</div>
@endsection
