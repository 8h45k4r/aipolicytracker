<h1>Upcoming AI regulation dates{{ $j ? ': '.$j->name : '' }}</h1>
<ul>@forelse($deadlines as $d)<li><strong>{{ $d->displayDate() }}</strong> · <a href="{{ $d->policyInstrument->url() }}" target="_top">{{ $d->title }}</a> <span class="meta">{{ $d->policyInstrument->jurisdiction->name }}</span></li>@empty<li class="meta">No dated deadline scheduled.</li>@endforelse</ul>
<p><a href="{{ route('calendar') }}" target="_top">Calendar and .ics feed →</a></p>
