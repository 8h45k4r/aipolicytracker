@component('emails.site.layout', ['title' => 'AI policy digest', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Weekly digest · {{ $periodLabel }}</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 16px;">{{ $changes->count() }} {{ \Illuminate\Support\Str::plural('change', $changes->count()) }} in AI policy this week</h1>
@forelse($changes as $c)
<div style="padding:12px 0;border-top:1px solid #D8DEE8;">
<p style="margin:0 0 4px;font-size:12px;color:#5D6B7E;"><span style="font-family:'Space Mono',monospace;color:#002147;">{{ $c->occurred_on->format('j M Y') }}</span> · {{ $c->jurisdiction?->name }} · {{ ucfirst($c->impact_level) }}</p>
<p style="margin:0 0 6px;font-weight:600;color:#002147;"><a href="{{ $c->url() }}" style="color:#002147;text-decoration:none;">{{ $c->title }}</a></p>
<p style="margin:0 0 6px;">{{ $c->what_changed }}</p>
@if($c->practical_impact)<p style="margin:0 0 6px;"><strong>Practical impact:</strong> {{ $c->practical_impact }}</p>@endif
@if($c->official_source_url)<p style="margin:0;font-size:13px;"><a href="{{ $c->official_source_url }}" style="color:#006AAC;">Official source</a></p>@endif
</div>
@empty
<p style="margin:0 0 12px;">No changes were recorded for your topics this week.</p>
@endforelse
@if($deadlines->isNotEmpty())
<h2 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:18px;color:#002147;margin:24px 0 8px;">Upcoming application dates</h2>
@foreach($deadlines as $d)
<p style="margin:0 0 6px;"><span style="font-family:'Space Mono',monospace;color:#002147;">{{ $d->displayDate() }}</span> · <a href="{{ $d->policyInstrument->url() }}" style="color:#006AAC;">{{ $d->title }}</a> <span style="color:#5D6B7E;">({{ $d->policyInstrument->jurisdiction->name }})</span></p>
@endforeach
@endif
@if($incidents->isNotEmpty())
<h2 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:18px;color:#002147;margin:24px 0 8px;">AI incidents this week ({{ $incidentCount }} recorded)</h2>
@foreach($incidents as $i)
<p style="margin:0 0 6px;"><span style="font-family:'Space Mono',monospace;color:#002147;">{{ $i->occurred_on->format('j M') }}</span> · <a href="{{ $i->url() }}" style="color:#006AAC;">{{ $i->displayTitle() }}</a>@if($i->mit_domain) <span style="color:#5D6B7E;">({{ $i->mit_domain }})</span>@endif</p>
@endforeach
<p style="margin:6px 0 0;font-size:12px;color:#5D6B7E;">Source: AI Incident Database (CC BY-SA 4.0), classified with the MIT AI Risk Repository taxonomy. <a href="{{ route('risk.index') }}" style="color:#006AAC;">Full AI-risk picture</a></p>
@endif
<p style="margin:20px 0 0;font-size:13px;"><a href="{{ route('changes.index') }}" style="color:#006AAC;">Full change log</a> · <a href="{{ route('changes.feed') }}" style="color:#006AAC;">RSS</a></p>
@endcomponent
