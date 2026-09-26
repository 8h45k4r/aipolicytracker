<h1>AI regulation in {{ $j->nameWithArticle() }}</h1>
<p class="meta">{{ $counts['instruments'] }} {{ \Illuminate\Support\Str::plural('instrument', $counts['instruments']) }} recorded · {{ $counts['binding'] }} binding</p>
<p>{{ \Illuminate\Support\Str::limit($j->regulatory_status_summary, 220) }}</p>
<ul>@foreach($policies as $p)<li><a href="{{ $p->url() }}" target="_top">{{ $p->short_title ?: $p->title }}</a> <span class="badge {{ $p->is_binding ? 'b' : '' }}">{{ $p->statusEnum()->label() }}</span></li>@endforeach</ul>
<p><a href="{{ $j->url() }}" target="_top">Full record →</a></p>
