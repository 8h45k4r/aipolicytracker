@extends('site.layouts.app')
@section('content')
<div class="container-site py-16 max-w-2xl">
    <p class="eyebrow">Template updates</p>
    @if(! $done)
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-navy">Stop template update emails</h1>
        <p class="mt-4 text-brand-body leading-7">Stop emailing {{ $email }} when a template gets a new version? This covers every template; it does not affect the weekly digest or anything else.</p>
        <form method="POST" action="{{ $action }}" class="mt-6">
            <button type="submit" class="btn-primary">Stop these emails</button>
        </form>
    @else
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-navy">Template update emails stopped</h1>
        <p class="mt-4 text-brand-body leading-7">{{ $email }} will not be emailed about new template versions. Ticking the box again on a later template request turns them back on for that template.</p>
        <p class="mt-6"><a href="{{ route('templates.index') }}" class="btn-secondary">Browse templates</a></p>
    @endif
</div>
@endsection
