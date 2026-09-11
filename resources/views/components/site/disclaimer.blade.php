<p {{ $attributes->merge(['class' => 'rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600']) }}>
    <strong class="text-slate-800">Informational only, not legal advice.</strong> {{ $slot->isEmpty() ? 'Verify every claim against the linked official sources and consult qualified counsel before acting.' : $slot }}
</p>
