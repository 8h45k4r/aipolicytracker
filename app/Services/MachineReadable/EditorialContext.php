<?php

namespace App\Services\MachineReadable;

use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TemplateVersion;
use App\Services\Templates\Records;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Support\Carbon;

/**
 * Markdown copies of the guides and the template pages, for assistants and answer engines,
 * in the shape of the record context files (RecordContext): the page's own text first, then
 * the records it rests on with their review state, then dates, licence and disclaimer.
 * Nothing here says more than the page.
 */
final class EditorialContext
{
    public function guide(string $slug): ?string
    {
        $page = config('content.guides.'.$slug);
        if (! $page) {
            return null;
        }
        $policies = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $page['policies'] ?? [])->get();
        $obligations = Obligation::published()->with('policyInstrument.jurisdiction')->whereIn('slug', $page['obligations'] ?? [])->get();

        $md = ['# '.$page['h1'], '', '- **Type**: Guide', '- **Page**: '.route('guides.show', $slug)];
        if (! empty($page['published'])) {
            $md[] = '- **Published**: '.$page['published'];
        }
        if (! empty($page['updated'])) {
            $md[] = '- **Text last updated**: '.$page['updated'];
        }
        $md = array_merge($md, ['', trim((string) $page['summary']), '']);
        foreach ($page['steps'] ?? [] as $i => $step) {
            $md[] = '## '.($i + 1).'. '.$step['title'];
            $md[] = '';
            $md[] = trim((string) $step['body']);
            $md[] = '';
        }
        if ($policies->isNotEmpty() || $obligations->isNotEmpty()) {
            $md[] = '## Records this guide is built from';
            $md[] = '';
            foreach ($policies as $p) {
                $md[] = '- '.($p->short_title ?: $p->title).' ('.$p->jurisdiction->name.'), '.$p->verificationLabel().': '.$p->url();
            }
            foreach ($obligations as $o) {
                $md[] = '- '.$o->title.' ('.($o->policyInstrument->short_title ?: $o->policyInstrument->title).'), '.$o->verificationLabel().': '.$o->url();
            }
            $md[] = '';
        }
        if (! empty($page['faq'])) {
            $md[] = '## Questions';
            $md[] = '';
            foreach ($page['faq'] as $f) {
                $md[] = '**'.$f['question'].'**';
                $md[] = '';
                $md[] = trim((string) $f['answer']);
                $md[] = '';
            }
        }

        return $this->footer($md);
    }

    public function template(string $slug): ?string
    {
        $meta = TemplateCatalog::find($slug);
        if (! $meta) {
            return null;
        }
        $version = TemplateVersion::latestFor($slug);
        $md = ['# '.$meta['title'], '', '- **Type**: Template ('.TemplateCatalog::typeLabel($meta['type']).')', '- **Page**: '.TemplateCatalog::url($slug),
            '- **Formats**: '.TemplateCatalog::formatList($meta)];
        if ($version) {
            $md[] = '- **Current version**: '.$version->label().($version->generated_at ? ', built '.$version->generated_at->toDateString() : '');
        }
        $md = array_merge($md, ['', trim((string) $meta['short']), '', '## What is inside', '']);
        foreach ($meta['inside'] ?? [] as $line) {
            $md[] = '- '.$line;
        }
        $md[] = '';
        $basis = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $meta['legal_basis'] ?? [])->get();
        if ($basis->isNotEmpty()) {
            $md[] = '## Laws and frameworks it is built from';
            $md[] = '';
            foreach ($basis as $p) {
                $md[] = '- '.($p->short_title ?: $p->title).' ('.$p->jurisdiction->name.'), '.$p->verificationLabel().': '.$p->url();
            }
            $md[] = '';
        }
        $duties = Records::obligations(TemplateCatalog::obligationFilter($meta))->take(40);
        if ($duties->isNotEmpty()) {
            $md[] = '## Recorded duties it covers';
            $md[] = '';
            foreach ($duties as $o) {
                $md[] = '- '.$o->title.' ('.($o->policyInstrument->short_title ?: $o->policyInstrument->title).'): '.$o->url();
            }
            $md[] = '';
        }
        if ($version && trim((string) $version->changelog) !== '') {
            $md[] = '## Latest changes';
            $md[] = '';
            $md[] = trim((string) $version->changelog);
            $md[] = '';
        }

        return $this->footer($md);
    }

    private function footer(array $md): string
    {
        $md[] = '- **Retrieved**: '.Carbon::now()->toDateString();
        $md[] = '- **Licence**: '.config('aipolicytracker.data_license_url');
        $md[] = '';
        $md[] = '> Built from structured summaries of official texts. Not legal advice. A record whose review status is not "verified" has not been confirmed against its official source by a named reviewer; open the official source before relying on any date or duty. How records are verified: '.route('verification').'.';

        return implode("\n", $md)."\n";
    }
}
