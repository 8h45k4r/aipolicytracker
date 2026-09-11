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
        return ['occurred_on' => 'date', 'snapshot_date' => 'date', 'deployers' => 'array', 'developers' => 'array', 'harmed' => 'array', 'sectors' => 'array', 'countries' => 'array'];
    }

    public function url(): string
    {
        return route('risk.incidents.show', $this->incident_id);
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

    /** Domain number (1-7) derived from the AIID label, via the MIT taxonomy file. */
    public static function domainLabels(): array
    {
        $mit = app(\App\Services\ExternalData\ExternalDataset::class)->mitRisk();

        return collect($mit['domains'] ?? [])->mapWithKeys(fn ($d) => [(string) $d['id'] => $d['aiid_domain_label']])->all();
    }
}
