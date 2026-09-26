<?php

namespace App\Services\Templates;

use App\Models\Obligation;
use Illuminate\Support\Collection;

/**
 * One template, described: the sheets its workbook has and the blocks its
 * document has, drawn from the records. A definition holds no rendering and
 * opens no file; TemplateBuilder hashes what it returns to decide whether the
 * template changed, and Workbook and Document render it.
 */
abstract class Definition
{
    public function __construct(protected readonly string $slug, protected readonly array $meta) {}

    /** @return list<array<string,mixed>> sheets (see Workbook) */
    public function sheets(): array
    {
        return [];
    }

    /** @return list<array<string,mixed>> blocks (see Document) */
    public function blocks(): array
    {
        return [];
    }

    /** The record links the template rests on, for the page and the README. */
    public function citations(): array
    {
        return [];
    }

    // ------------------------------------------------------------ helpers

    /** @param Collection<int,Obligation> $obligations */
    protected function dutiesSheet(string $name, Collection $obligations, ?string $note = null): array
    {
        return ['name' => $name, 'columns' => Records::obligationColumns(), 'rows' => Records::obligationRows($obligations), 'note' => $note];
    }

    protected function controlsSheet(string $name = 'Controls'): array
    {
        return ['name' => $name, 'columns' => Records::controlColumns(), 'rows' => Records::controlRows()];
    }

    /** @param list<string>|null $policies */
    protected function deadlinesSheet(?array $policies = null, string $name = 'Deadlines'): array
    {
        return ['name' => $name, 'columns' => Records::deadlineColumns(), 'rows' => Records::deadlineRows($policies)];
    }

    /** A document section per duty: the requirement, then the placeholder to meet it. */
    protected function dutySections(Collection $obligations, string $placeholder): array
    {
        $blocks = [];
        foreach ($obligations as $o) {
            $blocks[] = ['type' => 'h2', 'text' => $o->title];
            $blocks[] = ['type' => 'p', 'text' => trim((string) $o->summary)];
            if ($o->practical_action) {
                $blocks[] = ['type' => 'p', 'text' => 'In practice: '.trim($o->practical_action)];
            }
            if ($o->evidenceArtifacts->isNotEmpty()) {
                $blocks[] = ['type' => 'note', 'text' => 'Evidence a reviewer would expect: '.$o->evidenceArtifacts->pluck('title')->implode('; ').'.'];
            }
            $blocks[] = ['type' => 'placeholder', 'text' => $placeholder];
            $blocks[] = ['type' => 'cite', 'text' => Records::dutyLine($o), 'url' => $o->url()];
        }

        return $blocks;
    }

    protected function heading(string $text, int $level = 1): array
    {
        return ['type' => 'h'.$level, 'text' => $text];
    }

    protected function p(string $text): array
    {
        return ['type' => 'p', 'text' => $text];
    }

    protected function note(string $text): array
    {
        return ['type' => 'note', 'text' => $text];
    }

    protected function placeholder(string $text): array
    {
        return ['type' => 'placeholder', 'text' => $text];
    }

    /** @param list<string> $items */
    protected function list(array $items): array
    {
        return ['type' => 'list', 'items' => array_values($items)];
    }

    /** @param list<string> $header @param list<list<string>> $rows */
    protected function table(array $header, array $rows): array
    {
        return ['type' => 'table', 'header' => $header, 'rows' => $rows];
    }

    /** The recorded caveat on a record's dates, when the record carries one. */
    protected function dateCaveat(?string $policySlug): ?array
    {
        $p = $policySlug ? Records::policy($policySlug) : null;
        if (! $p) {
            return null;
        }
        $text = trim((string) ($p->status_note ?: $p->date_notes));

        return $text === '' ? null : $this->note('Dates as recorded on '.now()->format('j F Y').'. The record notes: '.$text);
    }

    protected static function col(string $key, string $label, int $width = 24, string $type = 'text', array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'width' => $width, 'type' => $type] + $extra;
    }

    protected static function select(string $key, string $label, array $options, int $width = 20, ?string $note = null): array
    {
        return ['key' => $key, 'label' => $label, 'width' => $width, 'type' => 'select', 'options' => array_values($options), 'note' => $note];
    }

    protected static function formula(string $key, string $label, string $formula, int $width = 16, ?string $note = null): array
    {
        return ['key' => $key, 'label' => $label, 'width' => $width, 'type' => 'formula', 'formula' => $formula, 'note' => $note];
    }

    /** Traffic-light rules on a numeric score column. */
    protected static function scoreRules(string $column, int $red = 15, int $amber = 8): array
    {
        return [
            ['column' => $column, 'op' => 'greaterThanOrEqual', 'value' => $red, 'fill' => 'F8D7DA'],
            ['column' => $column, 'op' => 'between', 'value' => [$amber, $red - 1], 'fill' => 'FFF3CD'],
            ['column' => $column, 'op' => 'lessThan', 'value' => $amber, 'fill' => 'D4EDDA'],
        ];
    }
}
