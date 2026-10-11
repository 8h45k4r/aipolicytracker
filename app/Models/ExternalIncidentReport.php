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
        return ['date_published' => 'date', 'synced_at' => 'datetime', 'authors' => 'array'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(ExternalIncident::class, 'incident_id', 'incident_id');
    }

    public function aiidUrl(): string
    {
        return 'https://incidentdatabase.ai/cite/'.$this->incident_id.'#r'.$this->report_number;
    }

    /**
     * Author names as AIID lists them, without e-mail addresses: a few upstream
     * entries carry a reporter's mailbox where the name should be, and the site
     * republishes this field.
     *
     * @param  array<int, mixed>  $authors
     * @return list<string>
     */
    public static function cleanAuthors(array $authors): array
    {
        $names = array_map(fn ($a) => mb_substr(trim((string) $a), 0, 120), $authors);

        return array_slice(array_values(array_filter($names, fn (string $a) => $a !== '' && ! preg_match('/\S+@\S+\.\S+/', $a))), 0, 6);
    }
}
