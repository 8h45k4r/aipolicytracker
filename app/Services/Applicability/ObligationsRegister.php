<?php

namespace App\Services\Applicability;

use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Services\Templates\DatasetVersion;
use App\Services\Templates\Records;
use App\Services\Templates\Workbook;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/**
 * The applicability check's result as an obligations register: one row per
 * screened duty, with its instrument, jurisdiction, actors, binding force,
 * source reference, evidence, controls and record link, plus columns to
 * fill (owner, status, evidence link). Rendered as XLSX (with dropdowns),
 * CSV, JSON or a short PDF. The state is the answers in the URL; nothing is
 * stored and no personal data is asked for.
 */
final class ObligationsRegister
{
    public const FORMATS = ['xlsx', 'csv', 'json', 'pdf'];

    public function __construct(private readonly ApplicabilityScreener $screener) {}

    /** @return array{answers:array, obligations:Collection<int,Obligation>, policies:Collection, rows:list<array>, labels:array} */
    public function build(array $answers): array
    {
        $result = $this->screener->screen($answers);
        $obligations = $result['obligations']->load(['evidenceArtifacts', 'frameworkMappings', 'controls']);
        $rows = array_map(fn ($o, $row) => $row + [
            'controls' => $o->controls->filter(fn ($c) => $c->published_at)->pluck('title')->implode('; '),
            'owner' => null, 'status' => null, 'evidence_link' => null, 'notes' => null,
        ], $obligations->all(), Records::obligationRows($obligations));

        return [
            'answers' => $answers,
            'obligations' => $obligations,
            'policies' => $result['policies'],
            'rows' => array_values($rows),
            'labels' => ['jurisdictions' => Jurisdiction::whereIn('slug', $answers['jurisdictions'])->pluck('name', 'slug')->all()],
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function columns(): array
    {
        $cols = Records::obligationColumns();
        $cols[] = ['key' => 'controls', 'label' => 'Recorded controls', 'width' => 40, 'type' => 'text'];
        $cols[] = ['key' => 'owner', 'label' => 'Owner', 'width' => 18, 'type' => 'text'];
        $cols[] = ['key' => 'status', 'label' => 'Status', 'width' => 14, 'type' => 'select', 'options' => ['Not assessed', 'Applies', 'Does not apply', 'In progress', 'Met'], 'note' => 'Your assessment; the register does not decide it.'];
        $cols[] = ['key' => 'evidence_link', 'label' => 'Evidence link', 'width' => 40, 'type' => 'url'];
        $cols[] = ['key' => 'notes', 'label' => 'Notes', 'width' => 40, 'type' => 'text'];

        return $cols;
    }

    public function xlsx(array $register): string
    {
        $readme = [
            'title' => 'Obligations register (applicability check)',
            'short' => 'The duties the applicability check screened for your answers, one per row, each cited to its record.',
            'inside' => ['Register sheet: every screened duty with instrument, jurisdiction, actors, binding force, source reference, evidence, framework references, recorded controls and a link to the record', 'Answers sheet: the answers the register was built from', 'Status dropdown, owner and evidence columns to complete'],
            'frameworks' => [],
            'version' => 'built '.now()->format('Y-m-d H:i').' UTC',
            'generated_at' => now()->format('Y-m-d'),
            'dataset_version' => DatasetVersion::current(),
            'url' => route('tools.applicability', array_filter($register['answers'], fn ($v) => $v !== null && $v !== [])),
            'licence' => config('templates.licence'),
            'disclaimer' => 'An educational screen over recorded scope, not a determination that any duty applies to you. '.config('templates.disclaimer'),
        ];
        $answers = [];
        foreach ($register['answers'] as $k => $v) {
            $answers[] = ['question' => str_replace('_', ' ', $k), 'answer' => is_array($v) ? implode(', ', array_map(fn ($s) => $register['labels']['jurisdictions'][$s] ?? $s, $v)) : (string) $v];
        }
        $sheets = [
            ['name' => 'Register', 'columns' => self::columns(), 'rows' => $register['rows'], 'rules' => [['column' => 'status', 'op' => 'equal', 'value' => 'Met', 'fill' => 'D4EDDA'], ['column' => 'status', 'op' => 'equal', 'value' => 'Applies', 'fill' => 'FFF3CD']]],
            ['name' => 'Answers', 'columns' => [['key' => 'question', 'label' => 'Question', 'width' => 24], ['key' => 'answer', 'label' => 'Answer', 'width' => 80]], 'rows' => $answers],
        ];
        $path = tempnam(sys_get_temp_dir(), 'register').'.xlsx';
        (new Workbook($readme, $sheets))->save($path);
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    public function csv(array $register): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, array_column(self::columns(), 'label'));
        foreach ($register['rows'] as $row) {
            fputcsv($fh, array_map(fn ($c) => $row[$c['key']] ?? '', self::columns()));
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    public function json(array $register): array
    {
        return [
            'data' => array_map(fn ($o, $row) => ['slug' => $o->slug, 'url' => $o->url()] + array_diff_key($row, array_flip(['owner', 'status', 'evidence_link', 'notes'])), $register['obligations']->all(), $register['rows']),
            'meta' => [
                'answers' => $register['answers'],
                'total' => count($register['rows']),
                'instruments_screened' => $register['policies']->filter(fn ($p) => $p['score'] > 0)->map(fn ($p) => ['slug' => $p['policy']->slug, 'title' => $p['policy']->short_title ?: $p['policy']->title, 'score' => $p['score'], 'why' => $p['why'], 'url' => $p['policy']->url()])->values()->all(),
                'downloads' => ['xlsx' => $this->url('xlsx', $register['answers']), 'csv' => $this->url('csv', $register['answers']), 'pdf' => $this->url('pdf', $register['answers'])],
                'page_url' => route('tools.applicability', array_filter($register['answers'], fn ($v) => $v !== null && $v !== [])),
                'license' => config('aipolicytracker.data_license'),
                'disclaimer' => 'An educational screen over recorded scope, not a determination that any duty applies.',
            ],
        ];
    }

    public function pdf(array $register): string
    {
        $html = view('site.tools.register-pdf', $register)->render();
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return (string) $pdf->output();
    }

    public function url(string $format, array $answers): string
    {
        return route('tools.applicability.register', ['format' => $format] + array_filter($answers, fn ($v) => $v !== null && $v !== []));
    }

    public static function mime(string $format): string
    {
        return match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv; charset=UTF-8',
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
