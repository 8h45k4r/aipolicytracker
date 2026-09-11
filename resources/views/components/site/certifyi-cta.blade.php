@props(['label' => 'Turn obligations into compliance workflows'])
<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-slate-300 p-4 text-sm']) }}>
    <p class="text-slate-700">{{ $label }} with <a href="{{ config('aipolicytracker.certifyi_url') }}" rel="noopener" class="font-medium text-slate-900" data-track="certifyi_click">Certifyi</a>, a separate compliance execution platform. AIPolicyTracker itself stays open and independent.</p>
</div>
