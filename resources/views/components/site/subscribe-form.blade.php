@props(['source' => 'site', 'topic' => null, 'topicLabel' => null, 'compact' => false])
{{-- Standalone on a page, the form is a tinted panel so the one block that asks the
     reader for something separates from the reference material around it. Inline in a
     record's sidebar (compact), it keeps the section rule the neighbouring blocks use. --}}
<form method="post" action="{{ route('subscribe.store') }}" {{ $attributes->merge(['class' => $compact ? 'rule-strong pt-3' : 'panel-tinted']) }} aria-label="Subscribe to the weekly AI policy digest">
    @csrf
    <input type="hidden" name="source" value="{{ $source }}">
    @if($topic)<input type="hidden" name="topics[]" value="{{ $topic }}">@endif
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
    <p class="eyebrow">{{ $topic ? 'Follow by email' : 'Weekly digest' }}</p>
    <p class="mt-1 text-sm text-brand-body">{{ $topic ? 'Get every dated, source-linked change to '.($topicLabel ?: 'this jurisdiction').' in a weekly email.' : 'Dated, source-linked AI policy changes and upcoming application dates, once a week. No marketing.' }}</p>
    @if(session('success'))<p class="mt-2 text-sm text-state-good" role="status">{{ session('success') }}</p>@endif
    @error('email')<p class="mt-2 text-sm text-state-bad" role="alert">{{ $message }}</p>@enderror
    <div class="mt-3 flex gap-2 {{ $compact ? 'flex-col' : 'max-w-md' }}">
        <label for="sub-email-{{ $source }}" class="sr-only">Email address</label>
        <input id="sub-email-{{ $source }}" type="email" name="email" required class="input flex-1" placeholder="you@example.org" autocomplete="email">
        <button type="submit" class="btn-primary">{{ $topic ? 'Follow' : 'Subscribe' }}</button>
    </div>
    <p class="mt-2 meta">Double opt-in; one-click unsubscribe in every email. <a href="{{ route('subscribe.show') }}">Choose jurisdictions</a></p>
</form>
