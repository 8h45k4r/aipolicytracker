<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Obligations register</title>
<style>
body{font-family:"DejaVu Sans",sans-serif;font-size:9pt;color:#1f2937;margin:24px}
h1{font-size:16pt;color:#002147;margin:0 0 4px}.meta{color:#6b7280;font-size:8.5pt}
table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border-bottom:1px solid #e5e7eb;padding:4px 3px;text-align:left;vertical-align:top;font-size:8.5pt}th{background:#f3f5f8}
.note{font-size:8pt;color:#6b7280;margin-top:10px}
</style></head><body>
<h1>Obligations register</h1>
<p class="meta">Generated {{ now()->format('j F Y') }} by aipolicytracker.org from the applicability check · Markets: {{ collect($answers['jurisdictions'])->map(fn ($s) => $labels['jurisdictions'][$s] ?? $s)->join(', ') }}
@if($answers['role']) · Role: {{ str_replace('_', ' ', $answers['role']) }} @endif
@if($answers['use_case']) · Use case: {{ str_replace('_', ' ', $answers['use_case']) }} @endif
@if($answers['sector']) · Sector: {{ str_replace('_', ' ', $answers['sector']) }} @endif
</p>
<p>{{ count($rows) }} recorded {{ \Illuminate\Support\Str::plural('duty', count($rows)) }} screened as overlapping these answers, {{ collect($rows)->where('binding', 'Legal requirement')->count() }} of them legal requirements. Each cites its source reference and links to its record; the record links to the official text.</p>
<table><thead><tr><th style="width:26%">Duty</th><th style="width:16%">Instrument</th><th style="width:9%">Jurisdiction</th><th style="width:9%">Binding</th><th style="width:9%">Reference</th><th>Evidence a reviewer expects</th><th style="width:9%">Owner / status</th></tr></thead><tbody>
@foreach($rows as $r)
<tr><td>{{ $r['duty'] }}</td><td>{{ $r['instrument'] }}</td><td>{{ $r['jurisdiction'] }}</td><td>{{ $r['binding'] }}</td><td>{{ $r['reference'] }}</td><td>{{ \Illuminate\Support\Str::limit($r['evidence'], 140) }}</td><td></td></tr>
@endforeach
</tbody></table>
<p class="note">An educational screen over recorded scope: the duties whose recorded actors, use cases and sectors overlap the answers. It is not a determination that any duty applies, and nothing here is legal advice. Nothing about you is stored; the answers live in the address of the page.</p>
</body></html>
