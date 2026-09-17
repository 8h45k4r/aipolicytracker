<?php

namespace App\Services\MachineReadable;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Database\Eloquent\Model;

/**
 * Renders one published record as a Markdown context file.
 *
 * The audience is a model or an agent that has been handed a question about a
 * specific instrument and needs the record in its context window without
 * scraping a page or reassembling a JSON document. `/policies/eu-ai-act.md` is
 * one file that answers "what does this record say, and how much should I trust
 * it".
 *
 * Every document ends with the same provenance block: the official source, the
 * review status, the confidence level, the date the facts were last confirmed,
 * the licence and the line that this is not legal advice. That block is not
 * decoration. A context file is the format most likely to be quoted back
 * without its page around it, so the caveats travel inside the file or they do
 * not travel at all.
 */
class RecordContext
{
    public function render(Model $record): string
    {
        return match (true) {
            $record instanceof PolicyInstrument => $this->policy($record),
            $record instanceof Jurisdiction => $this->jurisdiction($record),
            $record instanceof Obligation => $this->obligation($record),
            $record instanceof ChangeEvent => $this->change($record),
            default => throw new \InvalidArgumentException('No context format for '.$record::class),
        };
    }

    private function policy(PolicyInstrument $p): string
    {
        $lines = [
            '# '.$p->title."\n",
            $this->frontMatter([
                'Record type' => 'Policy instrument',
                'Jurisdiction' => $p->jurisdiction?->name,
                'Instrument type' => $p->typeEnum()->label(),
                'Status' => $p->statusEnum()->label(),
                'Binding' => $p->is_binding ? 'Yes' : 'No — guidance, strategy or standard',
                'Issuing body' => $p->issuing_body,
                'Adopted' => $this->date($p->adopted_on),
                'Published' => $this->date($p->published_on),
                'In force' => $this->date($p->in_force_on),
                'Applies from' => $this->date($p->applies_from),
            ]),
        ];

        $lines[] = $this->section('Summary', $p->summary_plain);
        $lines[] = $this->section('Scope', $p->scope_summary);
        $lines[] = $this->section('Who it applies to', $p->who_it_applies_to);
        $lines[] = $this->section('What organisations must do', $p->what_organizations_must_do);
        $lines[] = $this->section('Key dates', $p->key_dates_summary);
        $lines[] = $this->section('Penalties', $p->penalties_summary);
        $lines[] = $this->section('Notes on dates', $p->date_notes);

        $obligations = $p->obligations()->published()->orderBy('sort_order')->orderBy('title')->get();
        if ($obligations->isNotEmpty()) {
            $body = $obligations->map(function (Obligation $o) {
                $ref = $o->source_reference ? ' ('.$o->source_reference.')' : '';

                return '- **'.$o->title.'**'.$ref.($o->summary ? ' — '.$this->flatten($o->summary) : '')
                    ."\n  - Binding: ".($o->is_binding ? 'yes' : 'no')
                    .($o->applies_from ? "\n  - Applies from: ".$this->date($o->applies_from) : '')
                    ."\n  - Record: ".$o->url();
            })->implode("\n");
            $lines[] = $this->section('Obligations recorded against this instrument ('.$obligations->count().')', $body, raw: true);
        }

        $deadlines = $p->deadlines()->orderBy('due_on')->get();
        if ($deadlines->isNotEmpty()) {
            $body = $deadlines->map(fn ($d) => '- '.($d->due_on ? $this->date($d->due_on) : 'date not set')
                .' — '.$d->title
                .' (precision: '.($d->date_precision ?? 'exact').', status: '.($d->status ?? 'scheduled').')')->implode("\n");
            $lines[] = $this->section('Dated deadlines', $body, raw: true);
        }

        $lines[] = $this->provenance($p, $p->url());

        return $this->join($lines);
    }

    private function jurisdiction(Jurisdiction $j): string
    {
        $lines = [
            '# '.$j->name."\n",
            $this->frontMatter([
                'Record type' => 'Jurisdiction profile',
                'Short name' => $j->short_name,
                'ISO code' => $j->iso_code,
                'Type' => $j->jurisdiction_type,
                'Region' => trim(($j->region ?? '').($j->subregion ? ' / '.$j->subregion : '')) ?: null,
            ]),
        ];

        $lines[] = $this->section('Overview', $j->overview);
        $lines[] = $this->section('Regulatory status', $j->regulatory_status_summary);
        $lines[] = $this->section('What is binding and what is guidance', $j->binding_vs_guidance);
        $lines[] = $this->section('Current priorities', $j->current_priorities);
        $lines[] = $this->section('Regulators', $this->bullets($j->regulators, ['name', 'role', 'url']), raw: true);
        $lines[] = $this->section('How to use this profile', $this->bullets($j->how_to_use), raw: true);
        $lines[] = $this->section('Official sources', $this->bullets($j->official_sources, ['title', 'publisher', 'url']), raw: true);

        $policies = $j->policyInstruments()->published()->orderBy('title')->get();
        if ($policies->isNotEmpty()) {
            $body = $policies->map(fn (PolicyInstrument $p) => '- **'.$p->title.'** — '
                .$p->typeEnum()->label().', '.$p->statusEnum()->label()
                .', '.($p->is_binding ? 'binding' : 'non-binding')
                ."\n  - Record: ".$p->url()
                ."\n  - Context file: ".route('policies.context', $p->slug))->implode("\n");
            $lines[] = $this->section('Instruments recorded for this jurisdiction ('.$policies->count().')', $body, raw: true);
        }

        $lines[] = $this->provenance($j, $j->url());

        return $this->join($lines);
    }

    private function obligation(Obligation $o): string
    {
        $instrument = $o->policyInstrument;
        $lines = [
            '# '.$o->title."\n",
            $this->frontMatter([
                'Record type' => 'Obligation',
                'Instrument' => $instrument?->title,
                'Jurisdiction' => $instrument?->jurisdiction?->name,
                'Category' => $o->category,
                'Binding' => $o->is_binding ? 'Yes' : 'No',
                'Applies from' => $this->date($o->applies_from),
                'Provision' => $o->source_reference,
            ]),
        ];

        $lines[] = $this->section('What the duty requires', $o->summary);
        $lines[] = $this->section('What an organisation does about it', $o->practical_action);
        if ($instrument) {
            $lines[] = $this->section('Parent instrument', '- '.$instrument->title."\n  - Record: ".$instrument->url()."\n  - Context file: ".route('policies.context', $instrument->slug), raw: true);
        }

        $lines[] = $this->provenance($o, $o->url(), $instrument?->official_source_url);

        return $this->join($lines);
    }

    private function change(ChangeEvent $c): string
    {
        $lines = [
            '# '.$c->title."\n",
            $this->frontMatter([
                'Record type' => 'Change log entry',
                'Occurred on' => $this->date($c->occurred_on),
                'Jurisdiction' => $c->jurisdiction?->name,
                'Instrument' => $c->policyInstrument?->title,
                'Impact level' => $c->impactEnum()->label(),
                'Status after the change' => $c->statusAfterEnum()?->label(),
            ]),
        ];

        $lines[] = $this->section('What changed', $c->what_changed);
        $lines[] = $this->section('What it means in practice', $c->practical_impact);
        $lines[] = $this->provenance($c, route('changes.year', $c->occurred_on->year).'#'.$c->slug);

        return $this->join($lines);
    }

    /** @param array<string, ?string> $pairs */
    private function frontMatter(array $pairs): string
    {
        $rows = [];
        foreach ($pairs as $label => $value) {
            if ($value !== null && $value !== '') {
                $rows[] = '- **'.$label.'**: '.$this->flatten((string) $value);
            }
        }

        return implode("\n", $rows);
    }

    /**
     * Renders a stored list as Markdown bullets.
     *
     * A jurisdiction's regulators and official sources are lists of objects, not
     * paragraphs; flattening them into a sentence would lose the link that makes
     * each entry checkable.
     *
     * @param  list<string>  $keys  Which fields of an object entry to show, in order.
     */
    private function bullets(mixed $value, array $keys = []): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }
        $rows = [];
        foreach ($value as $entry) {
            if (is_string($entry)) {
                $rows[] = '- '.$this->flatten($entry);

                continue;
            }
            if (! is_array($entry)) {
                continue;
            }
            $parts = [];
            foreach ($keys ?: array_keys($entry) as $key) {
                if (! empty($entry[$key]) && is_scalar($entry[$key])) {
                    $parts[] = $this->flatten((string) $entry[$key]);
                }
            }
            if ($parts !== []) {
                $rows[] = '- '.implode(' — ', $parts);
            }
        }

        return $rows === [] ? null : implode("\n", $rows);
    }

    private function section(string $heading, ?string $body, bool $raw = false): string
    {
        if ($body === null || trim($body) === '') {
            return '';
        }

        return "\n## ".$heading."\n\n".($raw ? $body : $this->flatten($body));
    }

    /**
     * The provenance block every context file ends with.
     *
     * A record that has never been confirmed says so in those words rather than
     * leaving the field out, because an absent line reads as "fine" to both a
     * person and a model.
     */
    private function provenance(Model $record, string $url, ?string $fallbackSource = null): string
    {
        $source = $record->official_source_url ?? $fallbackSource;
        $verified = $record->last_verified_at ?? null;

        $rows = [
            '- **Record page**: '.$url,
            '- **Official source**: '.($source ?: 'none recorded — this record is incomplete, see '.route('gaps')),
        ];
        if (! empty($record->source_title)) {
            $rows[] = '- **Source document**: '.$this->flatten((string) $record->source_title);
        }
        if (! empty($record->source_publisher)) {
            $rows[] = '- **Published by**: '.$this->flatten((string) $record->source_publisher);
        }
        if (! empty($record->source_reference)) {
            $rows[] = '- **Provision**: '.$this->flatten((string) $record->source_reference);
        }
        $rows[] = '- **Review status**: '.str_replace('_', ' ', (string) ($record->review_status ?? 'pending review'));
        $rows[] = '- **Confidence**: '.($record->confidence_level ?? 'not stated');
        $rows[] = '- **Facts last confirmed**: '.($verified ? $this->date($verified) : 'never confirmed against the official source');
        if (! empty($record->reviewed_by)) {
            $rows[] = '- **Confirmed by**: '.$this->flatten((string) $record->reviewed_by).' (see '.route('reviewers').')';
        }
        $rows[] = '- **Retrieved**: '.now()->toDateString();
        $rows[] = '- **Licence**: '.config('aipolicytracker.data_license_url');

        return "\n## Provenance\n\n".implode("\n", $rows)
            ."\n\n> This record is a structured summary with a link to the official text. It is not legal advice."
            .' Open the official source before relying on any date or duty. How current each record type must be is published at '
            .route('verification').'; what a record must carry at all is published at '.route('coverage').'.';
    }

    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    /** Collapses newlines so a stored paragraph cannot break the document's structure. */
    private function flatten(string $value): string
    {
        return trim(preg_replace('/\s*\R\s*/u', ' ', $value) ?? $value);
    }

    /** Drops the sections that had nothing to say, so a sparse record renders no empty headings. */
    private function join(array $lines): string
    {
        return rtrim(implode("\n", array_filter($lines, fn ($l) => trim((string) $l) !== '')))."\n";
    }
}
