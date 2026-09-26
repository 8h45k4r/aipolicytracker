<?php

namespace App\Services\Review;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Models\TransitionMeasure;
use App\Services\PolicyData\PolicyDataRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The kinds of record a reviewer can confirm and publish from the admin, and where
 * each one lives in data/ so a decision made in the queue can be written back.
 *
 * Adding a kind means adding one entry here: the queue, the bulk routes, the stored
 * verification and the export command all read this table.
 */
final class ReviewableTypes
{
    /** @var array<string, array{model: class-string<Model>, label: string, plural: string, attestation: string, context: string, title: string}> */
    public const TYPES = [
        'policy' => [
            'model' => PolicyInstrument::class, 'label' => 'Policy instrument', 'plural' => 'policy instruments', 'title' => 'title', 'context' => 'Jurisdiction',
            'attestation' => 'I opened the official source of every selected record',
        ],
        'jurisdiction' => [
            'model' => Jurisdiction::class, 'label' => 'Jurisdiction', 'plural' => 'jurisdictions', 'title' => 'name', 'context' => 'Region',
            'attestation' => 'I read every selected summary against the sources it cites',
        ],
        'control' => [
            'model' => Control::class, 'label' => 'Control', 'plural' => 'controls', 'title' => 'title', 'context' => 'Kind · duties',
            'attestation' => 'I checked the clause references of every selected control against the standards cited',
        ],
        'change' => [
            'model' => ChangeEvent::class, 'label' => 'Change log entry', 'plural' => 'change log entries', 'title' => 'title', 'context' => 'Occurred · jurisdiction',
            'attestation' => 'I opened the official source of every selected entry',
        ],
        'transition_measure' => [
            'model' => TransitionMeasure::class, 'label' => 'Transition measure', 'plural' => 'transition measures', 'title' => 'title', 'context' => 'Type · status',
            'attestation' => 'I opened the official source of every selected measure',
        ],
    ];

    public const REVIEW_STATUSES = ['verified', 'pending_review', 'needs_update'];

    public const CONFIDENCE_LEVELS = ['high', 'medium', 'low', 'unavailable'];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function has(?string $type): bool
    {
        return $type !== null && isset(self::TYPES[$type]);
    }

    /** @return class-string<Model> */
    public static function model(string $type): string
    {
        return self::TYPES[$type]['model'];
    }

    public static function label(string $type, bool $plural = false): string
    {
        return self::TYPES[$type][$plural ? 'plural' : 'label'];
    }

    public static function attestation(string $type): string
    {
        return self::TYPES[$type]['attestation'];
    }

    /** The column that holds the record's display name. */
    public static function titleColumn(string $type): string
    {
        return self::TYPES[$type]['title'];
    }

    public static function query(string $type): Builder
    {
        $query = self::model($type)::query();

        return match ($type) {
            'policy', 'change' => $query->with('jurisdiction'),
            'control' => $query->withCount('obligations'),
            default => $query,
        };
    }

    public static function find(string $type, string $slug): ?Model
    {
        return self::has($type) ? self::model($type)::where('slug', $slug)->first() : null;
    }

    /**
     * The YAML file that holds the record, or null when there is none to write to.
     * Change log entries share one file per year, so the caller also needs the slug
     * to find the entry inside it (see isListFile).
     */
    public static function file(string $type, string $slug, ?string $dataDir = null): ?string
    {
        $dir = rtrim($dataDir ?? app(PolicyDataRepository::class)->path(), '/');
        $path = match ($type) {
            'policy' => collect(glob($dir.'/policies/*/'.$slug.'.yaml'))->first(),
            'jurisdiction' => $dir.'/jurisdictions/'.$slug.'.yaml',
            'control' => $dir.'/controls/'.$slug.'.yaml',
            'transition_measure' => $dir.'/transition/measures/'.$slug.'.yaml',
            'change' => collect(glob($dir.'/changes/*.yaml'))
                ->first(fn ($f) => preg_match('/^\s*-\s+slug:\s*'.preg_quote($slug, '/').'\s*$/m', (string) file_get_contents($f)) === 1),
            default => null,
        };

        return $path && is_file($path) ? $path : null;
    }

    /** True when the record is one item of a list inside its file rather than the whole file. */
    public static function isListFile(string $type): bool
    {
        return $type === 'change';
    }
}
