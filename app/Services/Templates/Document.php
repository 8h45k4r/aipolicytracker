<?php

namespace App\Services\Templates;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * A document described as data, rendered to DOCX.
 *
 * Blocks are headings (with real heading styles, so Word's navigation pane
 * and table of contents work), paragraphs, bullet lists, tables, placeholders
 * (marked in colour and in square brackets, so none is left in a finished
 * document by accident) and citations that link to the record they rest on.
 * The first page is the README: version, dataset version, date, disclaimer,
 * licence.
 *
 * @phpstan-type Block array{type:'h1'|'h2'|'h3'|'p'|'list'|'placeholder'|'table'|'cite'|'note', text?:string, items?:list<string>, header?:list<string>, rows?:list<list<string>>, url?:string}
 */
final class Document
{
    /** @param list<Block> $blocks */
    public function __construct(private readonly array $readme, private readonly array $blocks) {}

    /** @return list<Block> */
    public function blocks(): array
    {
        return $this->blocks;
    }

    public function content(): array
    {
        return ['blocks' => $this->blocks];
    }

    /** @return list<array{level:int, text:string}> the outline, for the page preview */
    public function outline(): array
    {
        $out = [];
        foreach ($this->blocks as $b) {
            if (in_array($b['type'], ['h1', 'h2', 'h3'], true)) {
                $out[] = ['level' => (int) substr($b['type'], 1), 'text' => $b['text'] ?? ''];
            }
        }

        return $out;
    }

    public function save(string $path): void
    {
        $doc = new PhpWord;
        $doc->getDocInfo()->setCreator('aipolicytracker.org')->setTitle($this->readme['title'])->setDescription($this->readme['short'] ?? '');
        $doc->setDefaultFontName('Calibri');
        $doc->setDefaultFontSize(11);
        $doc->addTitleStyle(0, ['size' => 22, 'bold' => true, 'color' => '002147'], ['spaceAfter' => 240]);
        $doc->addTitleStyle(1, ['size' => 16, 'bold' => true, 'color' => '002147'], ['spaceBefore' => 360, 'spaceAfter' => 120]);
        $doc->addTitleStyle(2, ['size' => 13, 'bold' => true, 'color' => '002147'], ['spaceBefore' => 240, 'spaceAfter' => 80]);
        $doc->addTitleStyle(3, ['size' => 11, 'bold' => true], ['spaceBefore' => 160, 'spaceAfter' => 60]);
        $doc->addParagraphStyle('Body', ['spaceAfter' => 120, 'lineHeight' => 1.15]);
        $doc->addParagraphStyle('Small', ['spaceAfter' => 80]);
        $doc->addTableStyle('Grid', ['borderSize' => 4, 'borderColor' => 'BFC7D3', 'cellMargin' => 60], ['bgColor' => 'E8EDF3']);

        $section = $doc->addSection(['marginLeft' => 1134, 'marginRight' => 1134, 'marginTop' => 1134, 'marginBottom' => 1134]);
        $r = $this->readme;
        $section->addTitle($r['title'], 0);
        $section->addText($r['short'] ?? '', null, 'Body');
        $meta = $section->addTable('Grid');
        foreach ([['Version', (string) $r['version']], ['Generated', $r['generated_at']], ['Dataset version', $r['dataset_version']], ['Source', $r['url']], ['Licence', $r['licence']]] as [$k, $v]) {
            $meta->addRow();
            $meta->addCell(2400)->addText($k, ['bold' => true]);
            $cell = $meta->addCell(7200);
            if (str_starts_with($v, 'http')) {
                $cell->addLink($v, $v, ['color' => '006AAC', 'underline' => 'single']);
            } else {
                $cell->addText($v);
            }
        }
        $section->addTextBreak();
        $section->addText('Disclaimer', ['bold' => true]);
        $section->addText($r['disclaimer'], ['size' => 9, 'color' => '5D6B7E'], 'Small');
        $section->addText('Placeholders are shown as [PLACEHOLDER: …] in amber. Replace every one before the document is used; a search for "[PLACEHOLDER" finds any that remain.', ['size' => 9, 'color' => '5D6B7E'], 'Small');
        $section->addText('Contents', ['bold' => true]);
        $section->addTOC(['size' => 10], ['tabLeader' => 'dot'], 1, 2);
        $section->addPageBreak();

        foreach ($this->blocks as $b) {
            switch ($b['type']) {
                case 'h1': case 'h2': case 'h3':
                    $section->addTitle($b['text'] ?? '', (int) substr($b['type'], 1));
                    break;
                case 'p':
                    $section->addText($b['text'] ?? '', null, 'Body');
                    break;
                case 'note':
                    $section->addText($b['text'] ?? '', ['size' => 9, 'color' => '5D6B7E', 'italic' => true], 'Small');
                    break;
                case 'placeholder':
                    $section->addText('[PLACEHOLDER: '.($b['text'] ?? '').']', ['bold' => true, 'color' => 'B45309'], 'Body');
                    break;
                case 'list':
                    foreach ($b['items'] ?? [] as $item) {
                        $section->addListItem($item, 0, null, null, 'Body');
                    }
                    break;
                case 'cite':
                    $run = $section->addTextRun('Small');
                    $run->addText('Source: ', ['size' => 9, 'color' => '5D6B7E']);
                    if (! empty($b['url'])) {
                        $run->addLink($b['url'], $b['text'] ?? $b['url'], ['size' => 9, 'color' => '006AAC', 'underline' => 'single']);
                    } else {
                        $run->addText($b['text'] ?? '', ['size' => 9, 'color' => '5D6B7E']);
                    }
                    break;
                case 'table':
                    $table = $section->addTable('Grid');
                    $cols = max(1, count($b['header'] ?? []));
                    $width = intdiv(9600, $cols);
                    if (! empty($b['header'])) {
                        $table->addRow(null, ['tblHeader' => true]);
                        foreach ($b['header'] as $h) {
                            $table->addCell($width, ['bgColor' => 'E8EDF3'])->addText($h, ['bold' => true, 'size' => 9]);
                        }
                    }
                    foreach ($b['rows'] ?? [] as $row) {
                        $table->addRow();
                        foreach (array_pad(array_values($row), $cols, '') as $cell) {
                            $c = $table->addCell($width);
                            if (is_string($cell) && str_starts_with($cell, 'http')) {
                                $c->addLink($cell, $cell, ['size' => 9, 'color' => '006AAC']);
                            } else {
                                $c->addText((string) $cell, ['size' => 9]);
                            }
                        }
                    }
                    $section->addTextBreak();
                    break;
            }
        }

        $footer = $section->addFooter();
        $footer->addPreserveText($r['title'].' · v'.$r['version'].' · aipolicytracker.org · page {PAGE} of {NUMPAGES}', ['size' => 8, 'color' => '5D6B7E'], ['alignment' => Jc::CENTER]);

        IOFactory::createWriter($doc, 'Word2007')->save($path);
    }
}
