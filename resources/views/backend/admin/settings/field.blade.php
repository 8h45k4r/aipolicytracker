{{-- One setting in its group's form: label, where the value comes from, the input, the
     error from the last save and the hint. A secret is never put back in the page. --}}
@php
    $v = $values[$key];
    $meta = $v['meta'];
    $id = 'f-'.$key;
    $invalid = $errors->has($key);
    $describedBy = trim(($invalid ? $id.'-error ' : '').$id.'-hint');
    $type = match (true) {
        $meta['secret'] => 'password',
        in_array($key, ['stale_after_days', 'funding_threshold'], true) => 'number',
        in_array($key, ['mail_from_address', 'contact_email'], true) => 'email',
        default => 'text',
    };
    $mono = $meta['secret'] || str_contains($key, 'dodo_product') || str_contains($key, 'turnstile') || in_array($key, ['dataset_doi', 'google_analytics_id', 'google_site_verification', 'bing_site_verification'], true);
@endphp
<div class="grid gap-1 sm:grid-cols-12 sm:gap-4 items-start" data-setting="{{ $key }}">
    <div class="sm:col-span-4 sm:pt-2">
        <label for="{{ $id }}" class="label">{{ $meta['label'] }}</label>
        @include('backend.admin.settings.source', ['v' => $v])
    </div>
    <div class="sm:col-span-8">
        @if(isset($meta['options']))
        <select id="{{ $id }}" name="{{ $key }}" class="input" aria-describedby="{{ $describedBy }}" @if($invalid) aria-invalid="true" @endif>
            <option value="">{{ $v['env'] ? 'Use the environment ('.$v['env'].')' : 'Use the default' }}</option>
            @foreach($meta['options'] as $opt => $optLabel)<option value="{{ $opt }}" @selected((string) old($key, $v['value']) === (string) $opt)>{{ $optLabel }}</option>@endforeach
        </select>
        @elseif($meta['secret'])
        <input id="{{ $id }}" name="{{ $key }}" type="password" class="input font-mono" autocomplete="off" spellcheck="false" aria-describedby="{{ $describedBy }}" @if($invalid) aria-invalid="true" @endif placeholder="{{ $v['set'] ? 'Stored: '.$v['display'] : 'Not set' }}">
        @else
        <input id="{{ $id }}" name="{{ $key }}" type="{{ $type }}" @if($type === 'number') min="0" step="1" inputmode="numeric" @endif class="input @if($mono) font-mono @endif" autocomplete="off" spellcheck="false" aria-describedby="{{ $describedBy }}" @if($invalid) aria-invalid="true" @endif value="{{ old($key, $v['value']) }}" placeholder="{{ $v['env'] ? 'Environment: '.$v['env'] : 'Not set' }}">
        @endif
        @error($key)<p id="{{ $id }}-error" class="mt-1 text-xs font-medium text-state-bad" data-field-error>{{ $message }}</p>@enderror
        <p id="{{ $id }}-hint" class="meta mt-1">{{ $meta['hint'] }}@if($v['env']) · environment: {{ $v['env'] }}@endif
            @if($meta['secret'])
                @if($invalid) Paste it again: a secret is not kept after a failed save.@endif
                @if($v['set'])<label class="ml-1 inline-flex items-center gap-1"><input type="checkbox" name="clear[]" value="{{ $key }}"> clear the stored value</label>@endif
            @elseif($v['set'])
                · Empty it and save to fall back to {{ $v['env'] ? 'the environment' : 'the default' }}.
            @endif
        </p>
    </div>
</div>
