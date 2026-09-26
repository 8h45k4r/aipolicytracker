@component('emails.site.layout', ['title' => 'Daily alert', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Daily alert · {{ $periodLabel }}</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 16px;">{{ $changes->count() }} {{ \Illuminate\Support\Str::plural('change', $changes->count()) }} in the records you follow</h1>
@forelse($changes as $c)
<div style="padding:12px 0;border-top:1px solid #D8DEE8;">
<p style="margin:0 0 4px;font-size:12px;color:#5D6B7E;"><span style="font-family:'Space Mono',monospace;color:#002147;">{{ $c->occurred_on->format('j M Y') }}</span> · {{ $c->jurisdiction?->name }}@if($c->policyInstrument) · {{ $c->policyInstrument->short_title ?: $c->policyInstrument->title }}@endif · {{ ucfirst($c->impact_level) }}</p>
@if(!empty($reasons[$c->id]))<p style="margin:0 0 6px;font-size:12px;"><span style="background:#E7F0F7;color:#002147;padding:2px 6px;font-weight:600;">May affect: {{ implode(', ', $reasons[$c->id]) }}</span></p>@endif
<p style="margin:0 0 6px;font-weight:600;color:#002147;"><a href="{{ $c->policyInstrument?->url() ?? route('changes.index') }}" style="color:#002147;text-decoration:none;">{{ $c->title }}</a></p>
<p style="margin:0 0 6px;">{{ $c->what_changed }}</p>
@if($c->practical_impact)<p style="margin:0 0 6px;"><strong>Practical impact:</strong> {{ $c->practical_impact }}</p>@endif
@if($c->official_source_url)<p style="margin:0;font-size:13px;"><a href="{{ $c->official_source_url }}" style="color:#006AAC;">Official source</a></p>@endif
</div>
@empty
<p style="margin:0 0 12px;">No new changes were recorded for the records you follow; an application date is approaching.</p>
@endforelse
@if($deadlines->isNotEmpty())
<h2 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:18px;color:#002147;margin:24px 0 8px;">Application dates in the next {{ \App\Services\Alerts\AlertBuilder::DEADLINE_HORIZON_DAYS }} days</h2>
@foreach($deadlines as $d)
<p style="margin:0 0 6px;"><span style="font-family:'Space Mono',monospace;color:#002147;">{{ $d->displayDate() }}</span> · <a href="{{ $d->policyInstrument->url() }}" style="color:#006AAC;">{{ $d->title }}</a> <span style="color:#5D6B7E;">({{ $d->policyInstrument->jurisdiction->name }})</span></p>
@endforeach
@endif
<p style="margin:20px 0 0;font-size:13px;color:#5D6B7E;">Relevance is screening against the profile you saved, not legal advice: open the official source and confirm before acting. You receive this because your Pro account follows these records or saved a matching profile. <a href="{{ $manageUrl }}" style="color:#006AAC;">Manage what you follow</a> · <a href="{{ route('changes.index') }}" style="color:#006AAC;">Full change log</a> · <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('alerts.unsubscribe', ['user' => $user->id]) }}" style="color:#006AAC;">Stop these emails</a></p>
@endcomponent
