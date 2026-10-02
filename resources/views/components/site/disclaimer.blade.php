<p {{ $attributes->merge(['class' => 'rounded-sm border border-brand-line bg-brand-paper px-3 py-2 text-xs text-brand-muted']) }}>
    <strong class="text-brand-body">Disclaimer: informational only, not legal advice.</strong> {{ $slot->isEmpty() ? 'Verify every claim against the linked official sources and consult a qualified lawyer before acting.' : $slot }}
</p>
