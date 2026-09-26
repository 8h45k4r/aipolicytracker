<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>AI regulation dates that apply to you</title>
<style>
body{font-family:"DejaVu Sans",sans-serif;font-size:10.5pt;color:#1f2937;margin:28px}
h1{font-size:18pt;color:#002147;margin:0 0 4px}h2{font-size:12pt;color:#002147;margin:18px 0 6px}
.meta{color:#6b7280;font-size:9pt}table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border-bottom:1px solid #e5e7eb;padding:5px 4px;text-align:left;vertical-align:top;font-size:9.5pt}th{background:#f3f5f8}
.note{font-size:8.5pt;color:#6b7280;margin-top:12px}
</style></head><body>
<h1>AI regulation dates that apply to you</h1>
<p class="meta">Generated {{ now()->format('j F Y') }} by aipolicytracker.org · Markets: {{ collect($answers['jurisdictions'])->map(fn ($s) => $labels['jurisdictions'][$s] ?? $s)->join(', ') }}
@if($answers['role']) · Role: {{ $labels['roles'][$answers['role']] ?? $answers['role'] }} @endif
@if($answers['risk']) · Risk tier: {{ $labels['risks'][$answers['risk']] ?? $answers['risk'] }} @endif
</p>
<p>{{ $summary }}</p>
<h2>Timeline</h2>
<table><thead><tr><th style="width:15%">Date</th><th style="width:38%">Deadline</th><th style="width:27%">Instrument</th><th>Why shown</th></tr></thead><tbody>
@forelse($rows as $r)@php($d = $r['deadline'])
<tr>
<td>{{ $d->displayDate() }}
@if($r['revision'])<br><span class="meta">originally {{ $r['revision']->from_due_on?->format('j M Y') ?? ($r['revision']->from_label ?: 'undated') }}</span> @endif
</td>
<td>{{ $d->title }}
@if($d->source_reference)<br><span class="meta">{{ $d->source_reference }}</span> @endif
</td>
<td>{{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}<br><span class="meta">{{ $d->policyInstrument->jurisdiction->name }} · {{ $d->deadline_status }} · confidence {{ $d->confidence_level }}</span></td>
<td>{{ ucfirst(implode(' and ', $r['why'])) }}
@if($d->official_source_url)<br><span class="meta">{{ $d->official_source_url }}</span> @endif
</td>
</tr>
@empty<tr><td colspan="4">No recorded date matches these answers.</td></tr>@endforelse
</tbody></table>
<p class="note">A filter over the dates on record by the scope recorded for each instrument and duty; not legal advice and not a determination that any law applies to you. Every row links to its official source on the site: {{ route('deadlines.engine', array_filter($answers, fn ($v) => $v !== null && $v !== [])) }}</p>
</body></html>
