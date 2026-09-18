@extends('backend.layouts.app', ['title' => 'Authenticator and recovery codes'])
@section('content')
<h1 class="font-display text-2xl font-semibold text-brand-navy">Authenticator and recovery codes</h1>
@if(! empty($codes))
<section class="mt-6 card-flat p-5 border-state-warn/40">
    <h2 class="section-title !text-lg">Save these recovery codes now</h2>
    <p class="mt-1 text-sm text-brand-body">Each code signs you in once if you cannot use your authenticator. They are shown this once and are not stored in a readable form.</p>
    <ul class="mt-3 grid gap-2 sm:grid-cols-2 font-mono text-sm">@foreach($codes as $c)<li class="rounded-sm bg-brand-paper px-3 py-2 select-all">{{ $c }}</li>@endforeach</ul>
    <p class="mt-3 text-xs text-brand-muted">Print them or put them in a password manager, then leave this page.</p>
</section>
@else
<section class="mt-6 card-flat p-5">
    <h2 class="section-title !text-lg">Status</h2>
    <p class="mt-1 text-sm text-brand-body">Authenticator enrolled on {{ auth()->user()->two_factor_confirmed_at?->format('j M Y') }}. <strong>{{ $remaining }}</strong> recovery {{ \Illuminate\Support\Str::plural('code', $remaining) }} unused.</p>
</section>
@endif
<form method="post" action="{{ route('admin.two-factor.regenerate') }}" class="mt-6 card-flat p-5">@csrf
    <h2 class="section-title !text-lg">Generate new recovery codes</h2>
    <p class="mt-1 text-sm text-brand-body">Every existing code stops working. You will be asked for your password first.</p>
    <button type="submit" class="btn-secondary mt-3">Generate new codes</button>
</form>
@endsection
