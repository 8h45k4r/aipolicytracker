@extends('site.layouts.app')
@section('content')
<x-site.listing-shell :seo="$seo" :filters="$filters" :options="$options" :paginator="$policies" mode="policies"
    heading="{{ !empty($filters['jurisdiction']) && !str_contains($filters['jurisdiction'], ',') && ($j = $options['jurisdictions']->firstWhere('slug', $filters['jurisdiction'])) ? 'AI policies in '.$j->name : 'AI policy explorer' }}"
    intro="Search and filter source-backed AI laws, regulations, standards, guidance and consultations. Each record shows its status, key dates, verification state and official source.">
    @forelse($policies as $policy)
        <x-site.policy-row :policy="$policy" />
    @empty
        <div class="py-6"><x-site.empty :reset="route('policies.index')" /></div>
    @endforelse
</x-site.listing-shell>
@endsection
