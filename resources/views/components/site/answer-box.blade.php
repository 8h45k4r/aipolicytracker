@props(['text', 'facts' => [], 'label' => 'In brief'])
{{--
The answer first. One paragraph composed from the record's structured fields
(App\Services\Records\AnswerBox), then the key facts as a definition list. It
sits above everything else in the main column, so the first thing a reader or
an answer engine meets is the answer, and it is the first thing in the raw HTML.
--}}
<section {{ $attributes->merge(['class' => 'card-flat p-5']) }} aria-labelledby="answer-heading" data-answer-box>
    <h2 id="answer-heading" class="eyebrow">{{ $label }}</h2>
    <p class="mt-2 text-base sm:text-lg leading-relaxed text-brand-navy" data-answer>{{ $text }}</p>
    @if(!empty($facts))
    <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2" data-key-facts>
        @foreach($facts as $fact)
        <div class="flex gap-3 border-t border-brand-line pt-2">
            <dt class="w-36 shrink-0 text-brand-muted">{{ $fact['label'] }}</dt>
            <dd class="min-w-0 text-brand-body">@if(!empty($fact['href']))<a href="{{ $fact['href'] }}" class="text-brand-navy hover:underline break-words" @if(str_starts_with($fact['href'], 'http') && !str_starts_with($fact['href'], url('/'))) rel="noopener" data-track="source_click" @endif>{{ $fact['value'] }}</a>@else{{ $fact['value'] }}@endif</dd>
        </div>
        @endforeach
    </dl>
    @endif
</section>
