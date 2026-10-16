@props(['source' => 'pricing', 'title' => 'Ask about a licence, a team plan or coverage'])
{{--
    The licence and custom-coverage enquiry form, on /pricing and /open-data. Posts to
    LicenceEnquiryController, which stores the row before mailing the contact address, so
    the confirmation below appears only for an enquiry that exists. Errors use their own
    bag ("licence") so another form on the same page never shows them. A "Talk to us"
    link may preselect the tier with ?tier=team or ?tier=commercial.
--}}
@php($tiers = \App\Models\LicenceEnquiry::TIERS)
@php($chosen = old('tier', array_key_exists((string) request('tier'), $tiers) ? request('tier') : 'commercial'))
@php($bag = $errors->getBag('licence'))
<section id="licence" {{ $attributes->merge(['class' => 'card-flat p-4 sm:p-6 scroll-mt-24']) }} aria-labelledby="licence-heading">
    <h2 id="licence-heading" class="section-title">{{ $title }}</h2>
    <p class="mt-2 text-sm text-brand-body">Commercial use of the records is already allowed on every plan under <a href="{{ config('aipolicytracker.data_license_url') }}" rel="license noopener">{{ config('aipolicytracker.data_license') }}</a>, with attribution. Write to us for a white-label licence without attribution, a freshness commitment in writing, a team plan or a jurisdiction we do not cover yet. Nothing is charged until a written agreement is signed.</p>
    @if(session('licence_enquiry'))
    <p class="mt-4 rounded-md border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status" data-licence-enquiry-sent>{{ session('licence_enquiry') }}</p>
    @else
    <form method="post" action="{{ route('licence-enquiry.store') }}" class="mt-4 space-y-4" data-track="licence_enquiry_submit">
        @csrf
        <input type="hidden" name="source" value="{{ $source }}">
        @if($bag->any())<div class="rounded-md border border-state-bad/30 bg-state-badbg p-3 text-sm text-state-bad" role="alert"><p class="font-medium">Please fix the following:</p><ul class="mt-1 list-disc pl-5">@foreach($bag->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="le-name" class="label">Your name <span class="text-state-bad" aria-hidden="true">*</span></label><input id="le-name" name="name" class="input" required minlength="2" maxlength="120" autocomplete="name" value="{{ old('name') }}"></div>
            <div><label for="le-email" class="label">Work email <span class="text-state-bad" aria-hidden="true">*</span></label><input id="le-email" name="email" type="email" class="input" required maxlength="190" autocomplete="email" value="{{ old('email') }}">@if($bag->has('email'))<p class="mt-1 text-xs text-state-bad">{{ $bag->first('email') }}</p>@endif</div>
            <div><label for="le-org" class="label">Organisation <span class="text-state-bad" aria-hidden="true">*</span></label><input id="le-org" name="organization" class="input" required minlength="2" maxlength="160" autocomplete="organization" value="{{ old('organization') }}"></div>
            <div><label for="le-tier" class="label">Tier of interest <span class="text-state-bad" aria-hidden="true">*</span></label><select id="le-tier" name="tier" class="input" required>@foreach($tiers as $k => $label)<option value="{{ $k }}" @selected($chosen === $k)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <div><label for="le-use" class="label">What will you use the data for? <span class="text-state-bad" aria-hidden="true">*</span></label><textarea id="le-use" name="use_case" rows="4" class="input" required minlength="20" maxlength="3000" aria-describedby="le-use-help">{{ old('use_case') }}</textarea><p id="le-use-help" class="mt-1 text-xs text-brand-muted">The product or service, who sees the data, and how fresh it needs to be.</p>@if($bag->has('use_case'))<p class="mt-1 text-xs text-state-bad">{{ $bag->first('use_case') }}</p>@endif</div>
        <div><label for="le-coverage" class="label">Coverage you need that is missing <span class="text-brand-muted font-normal">(optional)</span></label><textarea id="le-coverage" name="coverage_request" rows="2" class="input" maxlength="2000" aria-describedby="le-coverage-help">{{ old('coverage_request') }}</textarea><p id="le-coverage-help" class="mt-1 text-xs text-brand-muted">Jurisdictions or instruments. A request is weighed like any reader's: what is added is published for everyone, and no agreement buys coverage or wording (see <a href="{{ route('funding') }}">funding</a>).</p></div>
        <div class="hidden" aria-hidden="true"><label for="le-website">Website</label><input id="le-website" name="website" tabindex="-1" autocomplete="off"></div>
        <p class="text-xs text-brand-muted">We use these details only to answer this enquiry. See the <a href="{{ config('aipolicytracker.links.privacy_policy') ?: route('privacy') }}">privacy notice</a>.</p>
        <x-site.turnstile />
        @if($bag->has('turnstile'))<p class="mt-1 text-sm text-state-bad" role="alert">{{ $bag->first('turnstile') }}</p>@endif
        <button type="submit" class="btn-primary">Send enquiry</button>
    </form>
    @endif
</section>
