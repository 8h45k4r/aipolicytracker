@props(['source' => 'site', 'topic' => null, 'topicLabel' => null, 'compact' => false])
{{-- Standalone on a page, the form is a tinted panel so the one block that asks the
     reader for something separates from the reference material around it. Inline in a
     record's sidebar (compact), it keeps the section rule the neighbouring blocks use. --}}
<form method="post" action="{{ route('subscribe.store') }}" {{ $attributes->merge(['class' => $compact ? 'rule-strong pt-3' : 'panel-tinted']) }} aria-label="Subscribe to the weekly AI policy digest">
    @csrf
    <input type="hidden" name="source" value="{{ $source }}">
    @if($topic)<input type="hidden" name="topics[]" value="{{ $topic }}">@endif
    <div class="hidden" aria-hidden="true"><label for="sub-website-{{ $source }}">Website</label><input id="sub-website-{{ $source }}" type="text" name="website" value="" tabindex="-1" autocomplete="off"></div>
    {{-- With a topic this is still the weekly digest, filtered; "Follow" read like the account follow button. --}}
    <p class="eyebrow">{{ $topic ? 'Weekly digest for '.($topicLabel ?: 'this jurisdiction') : 'Weekly digest' }}</p>
    <p class="mt-1 text-sm text-brand-body">{{ $topic ? 'Get every dated, source-linked change to '.($topicLabel ?: 'this jurisdiction').' in a weekly email.' : 'Dated, source-linked AI policy changes and upcoming application dates, once a week. No marketing.' }}</p>
    @if(session('success'))<p class="mt-2 text-sm text-state-good" role="status">{{ session('success') }}</p>@endif
    @error('email')<p class="mt-2 text-sm text-state-bad" role="alert">{{ $message }}</p>@enderror
    <div class="mt-3 flex gap-2 {{ $compact ? 'flex-col' : 'max-w-md' }}">
        <label for="sub-email-{{ $source }}" class="sr-only">Email address</label>
        <input id="sub-email-{{ $source }}" type="email" name="email" required class="input flex-1" placeholder="you@example.org" autocomplete="email" value="{{ old('email') }}">
        <button type="submit" class="btn-primary">{{ $topic ? 'Get the digest' : 'Subscribe' }}</button>
    </div>
    @error('terms')<p class="mt-2 text-sm text-state-bad" role="alert">{{ $message }}</p>@enderror
    <div class="mt-3 space-y-1.5 text-xs text-brand-body">
        <label class="flex items-start gap-2"><input type="checkbox" name="terms" value="1" required class="mt-0.5 rounded-sm border-brand-line text-brand-navy"> <span>I accept the <a href="{{ config('aipolicytracker.links.terms_of_use') ?: route('terms') }}">terms of use</a> and the <a href="{{ config('aipolicytracker.links.privacy_policy') ?: route('privacy') }}">privacy notice</a>.</span></label>
        <label class="flex items-start gap-2"><input type="checkbox" name="topics[]" value="templates" class="mt-0.5 rounded-sm border-brand-line text-brand-navy"> <span>Also email me when new templates and guides are published (optional).</span></label>
    </div>
    <x-site.turnstile class="mt-3" />
    <p class="mt-2 meta">Temporary or throwaway addresses are not accepted. Double opt-in; one-click unsubscribe in every email. <a href="{{ route('subscribe.show') }}">Choose jurisdictions</a></p>
</form>
