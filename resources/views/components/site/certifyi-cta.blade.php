@props(['label' => 'Turn obligations into compliance workflows'])
<div {{ $attributes->merge(['class' => 'rounded-sm border border-dashed border-brand-line p-4 text-sm']) }}>
    <p class="text-brand-body">{{ $label }} with <a href="{{ config('aipolicytracker.certifyi_url') }}" rel="noopener" class="font-medium text-brand-navy" data-track="certifyi_click">Certifyi</a>, a separate compliance execution platform. AIPolicyTracker itself stays open and independent.</p>
</div>
