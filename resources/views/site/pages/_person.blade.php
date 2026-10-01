{{-- One person on the People page: portrait, name, role, bio and the links they chose. --}}
{{-- The portrait is sized by its width/height attributes (the file is 400px square, shown at 112px), so it never depends on a utility being in the built stylesheet. --}}
<li class="card-flat p-4 flex items-start gap-4" id="{{ $person['slug'] }}">
    @if(!empty($person['photo']))
    <img src="{{ asset('images/team/'.$person['photo']) }}" alt="Portrait of {{ $person['name'] }}" width="112" height="112" class="shrink-0 rounded-sm object-cover" loading="lazy" decoding="async">
    @else
    <div class="shrink-0 rounded-sm bg-brand-line" style="width:112px;height:112px" aria-hidden="true"></div>
    @endif
    <div class="min-w-0">
        <h3 class="font-display text-lg font-semibold text-brand-navy">{{ $person['name'] }}</h3>
        <p class="meta mt-1">{{ $person['role'] }}</p>
        <p class="mt-2 text-sm leading-6 text-brand-body">{{ $person['bio'] }}</p>
        @if(!empty($person['links']) || !empty($person['reviewer']))
        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
            @foreach($person['links'] ?? [] as $link)
            <li><a href="{{ $link['url'] }}" rel="me noopener nofollow" class="text-brand-body hover:text-brand-navy">{{ $link['label'] }}</a></li>
            @endforeach
            @if(!empty($person['reviewer']))
            <li><a href="{{ route('reviewers.show', $person['reviewer']) }}" class="text-brand-body hover:text-brand-navy">Reviewer profile</a></li>
            @endif
        </ul>
        @endif
    </div>
</li>
