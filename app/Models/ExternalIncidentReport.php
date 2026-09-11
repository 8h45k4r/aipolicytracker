<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Metadata of a news report catalogued by the AI Incident Database for one incident (no article text). */
class ExternalIncidentReport extends Model
{
    protected $primaryKey = 'report_number';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_published' => 'date', 'authors' => 'array'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(ExternalIncident::class, 'incident_id', 'incident_id');
    }

    public function aiidUrl(): string
    {
        return 'https://incidentdatabase.ai/cite/'.$this->incident_id.'#r'.$this->report_number;
    }
}
