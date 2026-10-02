{{-- The template request form. The files are not linked from the page: they go to the
     work address given, as signed links valid for a week (TemplateDownloadRequest). --}}
<div id="download" class="mt-3 scroll-mt-24">
@if(session('template_requested'))
    <div class="rounded-sm border border-state-good/30 bg-state-goodbg p-3 text-sm text-brand-body" role="status">
        <p class="font-semibold text-state-good">Check your inbox</p>
        <p class="mt-1">We've sent the download links{{ is_string(session('template_requested')) ? ' to '.session('template_requested') : '' }}. They work for {{ \App\Models\TemplateDownloadRequest::LINK_DAYS }} days. Nothing there after a few minutes? Check spam, or request again below.</p>
    </div>
@endif
    <form method="post" action="{{ route('templates.request', $slug) }}" class="mt-3 space-y-2.5" aria-labelledby="request-heading" novalidate>
        @csrf
        <p id="request-heading" class="font-semibold text-brand-navy">Get the {{ $formats }} files by email</p>
        @if($errors->any())<div class="rounded-sm border border-state-bad/30 bg-state-badbg px-3 py-2 text-xs text-state-bad" role="alert">{{ $errors->first() }}</div>@endif
        <div><label for="tr-name" class="label !mb-0.5 !text-xs">Full name</label><input id="tr-name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" class="input !min-h-0 !py-1.5"></div>
        <div><label for="tr-email" class="label !mb-0.5 !text-xs">Work email</label><input id="tr-email" name="email" type="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email" class="input !min-h-0 !py-1.5" aria-describedby="tr-email-hint"><p id="tr-email-hint" class="meta mt-0.5">Your organisation's address. Personal and temporary mailboxes are not accepted; the links are sent here.</p></div>
        <div><label for="tr-company" class="label !mb-0.5 !text-xs">Company or organisation</label><input id="tr-company" name="company" value="{{ old('company') }}" required maxlength="160" autocomplete="organization" class="input !min-h-0 !py-1.5"></div>
        <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-1">
            <div><label for="tr-title" class="label !mb-0.5 !text-xs">Job title <span class="font-normal text-brand-muted">(optional)</span></label><input id="tr-title" name="job_title" value="{{ old('job_title') }}" maxlength="120" autocomplete="organization-title" class="input !min-h-0 !py-1.5"></div>
            <div><label for="tr-country" class="label !mb-0.5 !text-xs">Country <span class="font-normal text-brand-muted">(optional)</span></label><input id="tr-country" name="country" value="{{ old('country') }}" maxlength="80" autocomplete="country-name" class="input !min-h-0 !py-1.5"></div>
        </div>
        {{-- Honeypot: invisible to people, filled in by form-filling bots. --}}
        <div class="hidden" aria-hidden="true"><label for="tr-website">Website</label><input id="tr-website" name="website" tabindex="-1" autocomplete="off"></div>
        <label class="flex items-start gap-2 text-xs text-brand-body"><input type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-0.5"> <span>I accept the <a href="{{ config('aipolicytracker.links.terms_of_use') ?: route('terms') }}">terms of use</a> and the <a href="{{ config('aipolicytracker.links.privacy_policy') ?: route('privacy') }}">privacy notice</a>.</span></label>
        <label class="flex items-start gap-2 text-xs text-brand-body"><input type="checkbox" name="updates" value="1" @checked(old('updates')) class="mt-0.5"> <span>Email me when this template gets a new version (optional).</span></label>
        <x-site.turnstile />
        <button type="submit" class="btn-primary w-full" data-track="template_request" data-track-label="{{ $slug }}">Email me the download links</button>
        <p class="meta">Free, no account. Used to send the files and, if you ask, version updates; never sold or shared.</p>
    </form>
</div>
