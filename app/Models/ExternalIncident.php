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

    /** Domain number (1-7) derived from the AIID label, via the MIT taxonomy file. */
    public static function domainLabels(): array
    {
        $mit = app(\App\Services\ExternalData\ExternalDataset::class)->mitRisk();

        return collect($mit['domains'] ?? [])->mapWithKeys(fn ($d) => [(string) $d['id'] => $d['aiid_domain_label']])->all();
    }
}
