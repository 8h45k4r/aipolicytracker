<?php

namespace App\Console\Commands;

use App\Services\Templates\TemplateBuilder;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Console\Command;

/**
 * Rebuilds the templates library from the records. A template whose content
 * has not changed is left alone; one that changed gets the next version, a
 * changelog and a change event. Runs daily after the imports.
 */
class BuildTemplatesCommand extends Command
{
    protected $signature = 'templates:build {--only= : One template slug} {--force : Write a new version even when the content is unchanged}';

    protected $description = 'Generate the templates library (XLSX/DOCX) from the records, versioning what changed';

    public function handle(TemplateBuilder $builder): int
    {
        $only = $this->option('only');
        if ($only && ! TemplateCatalog::find($only)) {
            $this->error("Unknown template: {$only}");

            return self::FAILURE;
        }
        $slugs = $only ? [$only] : TemplateCatalog::all()->keys()->all();
        $changed = 0;
        foreach ($slugs as $slug) {
            $started = microtime(true);
            $result = $builder->build($slug, (bool) $this->option('force'));
            $v = $result['version'];
            $changed += $result['changed'] ? 1 : 0;
            $this->line(sprintf('%-46s %-4s %-10s %5.1fs  %s', $slug, $v->label(), $result['changed'] ? 'built' : 'unchanged', microtime(true) - $started, $result['changed'] ? $v->changelog : ''));
        }
        $this->info(sprintf('%d template(s) checked, %d built.', count($slugs), $changed));

        return self::SUCCESS;
    }
}
