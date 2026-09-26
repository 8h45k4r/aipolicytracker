<?php

namespace App\Services\Templates;

use App\Models\Control;
use App\Models\Deadline;
use App\Models\ExternalRisk;
use App\Models\FrameworkMapping;
use App\Models\Obligation;
use App\Models\PolicyInstrument;

/**
 * A short identifier for the state of the records the templates are built
 * from, stamped into every file, so a reader can tell which data a file came
 * from and two files can be compared.
 *
 * It is a hash of what each table holds and when it last moved, not of the
 * files: a template's own content hash (TemplateBuilder) decides whether the
 * template changed; this says which dataset it was built against.
 */
final class DatasetVersion
{
    public static function current(): string
    {
        $parts = [];
        foreach ([PolicyInstrument::class, Obligation::class, Control::class, Deadline::class, FrameworkMapping::class, ExternalRisk::class] as $model) {
            $q = $model::query();
            $parts[] = class_basename($model).':'.$q->count().':'.($q->max('updated_at') ?? '');
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 12);
    }
}
