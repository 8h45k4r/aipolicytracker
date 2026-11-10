@props(['licence' => null, 'licenceUrl' => null])
{{--
    One line under a record's citation or a template's licence: commercial reuse is already
    allowed under the open licence, and the paid route is for what that licence does not
    give (no attribution, freshness in writing). Defaults to the dataset licence; a page
    whose content carries another licence passes its own.
--}}
<p {{ $attributes->merge(['class' => 'text-sm text-brand-body']) }} data-commercial-use>Using this in a product? Commercial use is allowed under <a href="{{ $licenceUrl ?? config('aipolicytracker.data_license_url') }}" rel="license noopener">{{ $licence ?? config('aipolicytracker.data_license') }}</a>, with attribution. <a href="{{ route('pricing') }}#licence" data-track="commercial_use_click">Ask about an SLA-backed feed or a white-label licence</a>.</p>
