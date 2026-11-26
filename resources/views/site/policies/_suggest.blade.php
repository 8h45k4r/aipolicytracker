{{-- "Suggest a policy": four fields into the review queue (ContributeController::suggest). --}}
@php($bag = $errors->getBag('suggest'))
<section id="suggest-policy" class="container-site pb-8" aria-labelledby="suggest-policy-h" data-suggest-policy>
    <div class="grid gap-8 lg:grid-cols-4">
    <div class="hidden lg:block"></div>
    <div class="lg:col-span-3 card-flat p-4 sm:p-6">
        <h2 id="suggest-policy-h" class="section-title">Suggest a policy</h2>
        <p class="mt-1 text-sm text-brand-body">Don't see a law, regulation or guidance you are tracking? Send it and a reviewer will check it against the official source before it is added.</p>
        @if($bag->any())<div class="mt-3 rounded-md border border-state-bad/30 bg-state-badbg p-3 text-sm text-state-bad" role="alert"><ul class="list-disc pl-5">@foreach($bag->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        <form method="post" action="{{ route('policies.suggest') }}" class="mt-4 grid gap-4 sm:grid-cols-2" data-track="policy_suggest_submit">
            @csrf
            <div><label for="sg-name" class="label">Policy name <span class="text-state-bad" aria-hidden="true">*</span></label><input id="sg-name" name="policy_name" class="input" required minlength="3" maxlength="200" value="{{ old('policy_name') }}" placeholder="e.g. AI Safety Act 2025" @if($bag->has('policy_name')) aria-invalid="true" @endif></div>
            <div><label for="sg-jurisdiction" class="label">Jurisdiction <span class="text-state-bad" aria-hidden="true">*</span></label><select id="sg-jurisdiction" name="jurisdiction" class="input" required @if($bag->has('jurisdiction')) aria-invalid="true" @endif><option value="">Select jurisdiction</option>@foreach($suggestJurisdictions as $slug => $name)<option value="{{ $slug }}" @selected(old('jurisdiction') === $slug)>{{ $name }}</option>@endforeach<option value="other" @selected(old('jurisdiction') === 'other')>Other / not listed</option></select></div>
            <div class="sm:col-span-2"><label for="sg-url" class="label">Official source URL <span class="text-state-bad" aria-hidden="true">*</span></label><input id="sg-url" name="official_source_url" type="url" class="input" required maxlength="2048" value="{{ old('official_source_url') }}" placeholder="https://…" aria-describedby="sg-url-help" @if($bag->has('official_source_url')) aria-invalid="true" @endif><p id="sg-url-help" class="mt-1 text-xs text-brand-muted">The government, legislature or regulator page that publishes it, not a news story.</p></div>
            <div class="sm:col-span-2"><label for="sg-notes" class="label">Notes <span class="font-normal text-brand-muted">(optional)</span></label><textarea id="sg-notes" name="notes" rows="2" class="input" maxlength="280" aria-describedby="sg-notes-count" data-char-limit>{{ old('notes') }}</textarea><p id="sg-notes-count" class="mt-1 text-xs text-brand-muted" aria-live="polite" data-char-count>{{ mb_strlen((string) old('notes', '')) }}/280</p></div>
            <div class="hidden" aria-hidden="true"><label for="sg-website">Website</label><input id="sg-website" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="sm:col-span-2"><x-site.turnstile /></div>
            <div class="sm:col-span-2 flex flex-wrap items-center gap-3"><button type="submit" class="btn-primary">Submit suggestion</button><a href="{{ route('contribute', ['type' => 'new_policy']) }}" class="text-sm">Send a fuller record instead</a></div>
        </form>
    </div>
    </div>
</section>
