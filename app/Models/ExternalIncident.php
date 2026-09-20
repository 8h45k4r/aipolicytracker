<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalIncident extends Model
{
    protected $primaryKey = 'incident_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'snapshot_date' => 'date', 'modified_at' => 'datetime', 'synced_at' => 'datetime', 'deployers' => 'array', 'developers' => 'array', 'harmed' => 'array', 'sectors' => 'array', 'countries' => 'array', 'entities' => 'array', 'implicated_systems' => 'array', 'similar_incidents' => 'array'];
    }

    public function reports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExternalIncidentReport::class, 'incident_id', 'incident_id')->orderBy('date_published')->orderBy('report_number');
    }

    public function url(): string
    {
        return route('risk.incidents.show', $this->incident_id);
    }

    /**
     * The same threshold as every other record type, stated rather than assumed.
     *
     * Every one of the 1,663 incidents currently carries a description, so this
     * excludes nothing today and the sitemap is unchanged by it. That is the
     * point: the incident corpus was passing a test nobody had written, and a
     * test nobody has written cannot fail when a future import brings in a record
     * with no description. An entry that arrives empty is now served
     * noindex,follow instead of being offered as a result with nothing on it.
     */
    public function isIndexable(): bool
    {
        return filled($this->description);
    }

    /** AIID Discover view listing every report on this incident (the reports themselves stay on AIID). */
    public function reportsUrl(): string
    {
        return 'https://incidentdatabase.ai/apps/discover/?incident_id='.$this->incident_id;
    }

    public function citeUrl(): string
    {
        return 'https://incidentdatabase.ai/cite/'.$this->incident_id;
    }

    /** AIID entity page for an entity id from the live sync. */
    public static function entityUrl(string $entityId): string
    {
        return 'https://incidentdatabase.ai/entities/'.rawurlencode($entityId);
    }

    /** Ids of incidents AIID editors or its similarity model relate to this one (editor picks first). */
    public function similarIds(int $limit = 6): array
    {
        $s = $this->similar_incidents ?? [];
        $ids = array_map('intval', $s['editor'] ?? []);
        foreach ($s['nlp'] ?? [] as $n) {
            $ids[] = (int) ($n['id'] ?? 0);
        }

        return array_slice(array_values(array_unique(array_filter($ids, fn ($id) => $id > 0 && $id !== (int) $this->incident_id))), 0, $limit);
    }

    /** Names in the deployer/developer/harmed lists paired with their AIID entity ids when known. */
    public function entityList(string $role): array
    {
        $withIds = $this->entities[$role] ?? null;
        if ($withIds) {
            return $withIds;
        }

        return array_map(fn ($name) => ['id' => null, 'name' => $name], $this->{$role === 'harmed' ? 'harmed' : $role} ?? []);
    }

    /**
     * The actor chain as one readable sentence: who built it, who ran it, who it
     * hurt. Every word of it is "alleged", because that is how these records are
     * classified and a summary that quietly drops the qualifier would be making a
     * finding this project has not made.
     *
     * Built from the three fields that are populated on essentially every record
     * (developers and deployers 100%, harmed 99.9%). Fields like `countries` and
     * `implicated_systems` are deliberately not used here: they are populated on
     * 8.4% and 0% of records, so anything built on them renders blank far more
     * often than it renders.
     *
     * A sentence rather than three chip lists because a sentence is what a reader
     * skims and what an answer engine can quote.
     */
    public function actorLine(): ?string
    {
        $names = function (string $role, int $max = 2): ?string {
            // Each stored element can itself be a comma-joined list -- incident
            // 1466 holds all nine of its harmed parties in a single string -- so
            // splitting is what makes the cap mean anything. Without it the
            // sentence prints every name and stops being a summary.
            $all = [];
            foreach ($this->entityList($role) as $entity) {
                foreach (explode(',', (string) ($entity['name'] ?? '')) as $part) {
                    if (($part = trim($part)) !== '') {
                        $all[] = $part;
                    }
                }
            }
            $all = array_values(array_unique($all));
            if ($all === []) {
                return null;
            }
            $shown = array_slice($all, 0, $max);
            $rest = count($all) - count($shown);
            if ($rest > 0) {
                return implode(', ', $shown).' and '.$rest.' '.($rest === 1 ? 'other' : 'others');
            }

            return count($shown) === 2 ? $shown[0].' and '.$shown[1] : $shown[0];
        };

        $developer = $names('developers');
        $deployer = $names('deployers');
        $harmed = $names('harmed');

        $built = match (true) {
            $developer !== null && $deployer !== null && $developer === $deployer => 'An AI system built and deployed by '.$developer,
            $developer !== null && $deployer !== null => 'An AI system built by '.$developer.' and deployed by '.$deployer,
            $developer !== null => 'An AI system built by '.$developer,
            $deployer !== null => 'An AI system deployed by '.$deployer,
            default => null,
        };

        if ($built === null) {
            return null;
        }

        return $harmed !== null
            ? $built.' allegedly harmed '.$harmed.'.'
            : $built.'.';
    }

    /**
     * The period the catalogued coverage spans, e.g. "Mar 2019 - Jan 2021".
     *
     * Reads the loaded reports rather than issuing its own query, so a page that
     * already has them pays nothing. Null when no report carries a date, which is
     * the honest answer rather than inventing a range from the incident date.
     */
    public function reportSpan(): ?string
    {
        $dates = $this->reports->pluck('date_published')->filter();
        if ($dates->isEmpty()) {
            return null;
        }
        $first = $dates->min()->format('M Y');
        $last = $dates->max()->format('M Y');

        return $first === $last ? $first : $first.' - '.$last;
    }

    /** Domain number (1-7) derived from the AIID label, via the MIT taxonomy file. */
    public static function domainLabels(): array
    {
        $mit = app(\App\Services\ExternalData\ExternalDataset::class)->mitRisk();

        return collect($mit['domains'] ?? [])->mapWithKeys(fn ($d) => [(string) $d['id'] => $d['aiid_domain_label']])->all();
    }
}
