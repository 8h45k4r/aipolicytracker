{{-- An error inside /backend (App\Support\Admin\AdminErrorPage). With $admin, the admin
     layout and its sidebar; without (signed out, or not past the second factor), a
     standalone card that shows no admin navigation. --}}
@extends($admin ? 'backend.layouts.app' : 'backend.layouts.bare', ['title' => $heading])
@section('content')
<section class="mx-auto max-w-2xl {{ $admin ? 'mt-6 lg:mt-12' : '' }} card-flat p-6 sm:p-8" aria-labelledby="error-heading" data-admin-error="{{ $status }}">
    <p class="adm-label">{{ $eyebrow }}</p>
    <h1 id="error-heading" class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $heading }}</h1>
    <p class="mt-3 text-[15px] leading-relaxed text-brand-body">{{ $body }}</p>
    @if($detail)<p class="mt-2 text-sm text-brand-muted">{{ $detail }}</p>@endif
    @if($actions !== [])
    <div class="mt-6 flex flex-wrap gap-2">
        @foreach($actions as [$href, $label, $primary])<a href="{{ $href }}" class="{{ $primary ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>@endforeach
    </div>
    @endif
    @if($reference)<p class="mt-6 border-t border-brand-line pt-4 text-xs text-brand-muted">Reference <code class="font-mono">{{ $reference }}</code>: quote it when you report this, and it finds the log entry.</p>@endif
</section>
@endsection
