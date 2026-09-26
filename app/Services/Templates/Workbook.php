<?php

namespace App\Services\Templates;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A workbook described as data, rendered to XLSX.
 *
 * A builder says what the sheets are: columns (with a type, options for a
 * dropdown, a formula), rows, colour rules, and notes. This turns that into a
 * file with a README sheet first, styled and frozen header rows, filters,
 * dropdowns backed by a hidden lists sheet (so long lists work), formulas
 * copied down, and conditional formatting. Keeping the description separate
 * from the rendering is what lets the page preview the sheet and the content
 * be hashed for versioning without opening the file.
 *
 * @phpstan-type Column array{key:string, label:string, width?:int, type?:'text'|'date'|'number'|'select'|'formula'|'url', options?:list<string>, formula?:string, note?:string}
 * @phpstan-type Sheet array{name:string, columns:list<Column>, rows:list<array<string,mixed>>, rules?:list<array{column:string, op:string, value:string|int|float, fill:string}>, note?:string, editable_rows?:int}
 */
final class Workbook
{
    /** @var list<Sheet> */
    private array $sheets = [];

    private const NAVY = '002147';

    private const PAPER = 'F3F5F8';

    /** @param list<Sheet> $sheets */
    public function __construct(private readonly array $readme, array $sheets)
    {
        $this->sheets = $sheets;
    }

    /** @return list<Sheet> */
    public function sheets(): array
    {
        return $this->sheets;
    }

    /** The content that makes this workbook what it is, for hashing and preview. */
    public function content(): array
    {
        return ['sheets' => $this->sheets];
    }

    public function save(string $path): void
    {
        $ss = new Spreadsheet;
        $ss->getProperties()->setCreator('aipolicytracker.org')->setTitle($this->readme['title'])->setDescription($this->readme['short'] ?? '')->setKeywords('AI governance, template, '.implode(', ', $this->readme['frameworks'] ?? []));
        $ss->removeSheetByIndex(0);

        $this->readmeSheet($ss);
        $lists = $ss->createSheet();
        $lists->setTitle('Lists');
        $listColumn = 0;

        foreach ($this->sheets as $sheet) {
            $ws = $ss->createSheet();
            $ws->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $sheet['name']), 0, 31));
            $columns = $sheet['columns'];
            $lastCol = self::col(count($columns));

            $ws->fromArray(array_map(fn ($c) => $c['label'], $columns), null, 'A1');
            $ws->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $ws->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
            $ws->getStyle("A1:{$lastCol}1")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $ws->getRowDimension(1)->setRowHeight(30);
            $ws->freezePane('A2');
            $ws->setAutoFilter("A1:{$lastCol}1");

            $rowsOut = [];
            foreach ($sheet['rows'] as $row) {
                $rowsOut[] = array_map(fn ($c) => ($c['type'] ?? 'text') === 'formula' ? null : ($row[$c['key']] ?? null), $columns);
            }
            if ($rowsOut !== []) {
                $ws->fromArray($rowsOut, null, 'A2');
            }
            $editable = max(count($rowsOut), $sheet['editable_rows'] ?? 0);
            $last = max(2, $editable + 1);

            foreach ($columns as $i => $c) {
                $letter = self::col($i + 1);
                $ws->getColumnDimension($letter)->setWidth($c['width'] ?? max(12, min(48, mb_strlen($c['label']) + 4)));
                $ws->getStyle("{$letter}2:{$letter}".$last)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                if (! empty($c['note'])) {
                    $ws->getComment("{$letter}1")->getText()->createTextRun($c['note']);
                }
                if (($c['type'] ?? '') === 'select' && ! empty($c['options'])) {
                    $listColumn++;
                    $lc = self::col($listColumn);
                    $lists->setCellValue("{$lc}1", $sheet['name'].': '.$c['label']);
                    foreach (array_values($c['options']) as $n => $opt) {
                        $lists->setCellValue("{$lc}".($n + 2), $opt);
                    }
                    $ref = "Lists!\${$lc}\$2:\${$lc}\$".(count($c['options']) + 1);
                    $v = new DataValidation;
                    $v->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)->setShowInputMessage(true)->setShowErrorMessage(true)->setShowDropDown(true)->setErrorTitle('Choose from the list')->setError('Pick one of the listed values.')->setFormula1($ref);
                    $ws->setDataValidation("{$letter}2:{$letter}".$last, $v);
                }
                if (($c['type'] ?? '') === 'date') {
                    $ws->getStyle("{$letter}2:{$letter}".$last)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                }
                if (($c['type'] ?? '') === 'formula' && ! empty($c['formula'])) {
                    for ($r = 2; $r <= $last; $r++) {
                        $ws->setCellValue("{$letter}{$r}", str_replace('{r}', (string) $r, $c['formula']));
                    }
                    $ws->getStyle("{$letter}2:{$letter}".$last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::PAPER);
                }
                if (($c['type'] ?? '') === 'url') {
                    foreach ($rowsOut as $n => $row) {
                        $val = $row[$i] ?? null;
                        if (is_string($val) && str_starts_with($val, 'http')) {
                            $ws->getCell("{$letter}".($n + 2))->getHyperlink()->setUrl($val);
                            $ws->getStyle("{$letter}".($n + 2))->getFont()->getColor()->setRGB('006AAC');
                        }
                    }
                }
            }

            foreach ($sheet['rules'] ?? [] as $rule) {
                $idx = array_search($rule['column'], array_column($columns, 'key'), true);
                if ($idx === false) {
                    continue;
                }
                $letter = self::col($idx + 1);
                $range = "{$letter}2:{$letter}".$last;
                $cond = new Conditional;
                $cond->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType($rule['op']);
                foreach ((array) $rule['value'] as $value) {
                    $cond->addCondition(is_string($value) ? '"'.$value.'"' : (string) $value);
                }
                $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB($rule['fill']);
                $styles = $ws->getStyle($range)->getConditionalStyles();
                $styles[] = $cond;
                $ws->getStyle($range)->setConditionalStyles($styles);
            }

            if (! empty($sheet['note'])) {
                $noteRow = $last + 2;
                $ws->setCellValue("A{$noteRow}", $sheet['note']);
            }
        }

        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        $ss->setActiveSheetIndex(0);
        IOFactory::createWriter($ss, 'Xlsx')->save($path);
        $ss->disconnectWorksheets();
    }

    private function readmeSheet(Spreadsheet $ss): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle('README');
        $r = $this->readme;
        $rows = [
            [$r['title']],
            [''],
            ['Version', $r['version']],
            ['Generated', $r['generated_at']],
            ['Dataset version', $r['dataset_version']],
            ['Source', $r['url']],
            ['Licence', $r['licence']],
            [''],
            ['What this is', $r['short'] ?? ''],
            [''],
            ['Disclaimer', $r['disclaimer']],
            [''],
            ['How it was made', 'Generated from the records on aipolicytracker.org. Every row that cites a duty links to the record it came from and the record links to the official source. When the records change the file is rebuilt and the version above changes; the page at the source URL lists every version and what changed.'],
        ];
        foreach ($r['inside'] ?? [] as $n => $line) {
            $rows[] = [$n === 0 ? 'Inside' : '', $line];
        }
        $ws->fromArray($rows, null, 'A1');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::NAVY);
        $ws->getStyle('A1:A40')->getFont()->setBold(true);
        $ws->getColumnDimension('A')->setWidth(20);
        $ws->getColumnDimension('B')->setWidth(110);
        $ws->getStyle('B1:B40')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $ws->getCell('B6')->getHyperlink()->setUrl($r['url']);
    }

    /** 1 => A, 27 => AA */
    public static function col(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m).$s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }
}
