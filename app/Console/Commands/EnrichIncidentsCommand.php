<?php

namespace App\Console\Commands;

use App\Models\ExternalIncident;
use App\Services\ExternalData\IncidentEnrichment;
use Illuminate\Console\Command;

/**
 * Recompute what this site adds to incident records: harm domain, related
 * laws, policy angle and sensitivity. The importers run the fill-only form
 * after every import; this is the operator's handle for the rest: after the
 * overrides file changes, or after new policy records land that an older
 * incident should now link to (--refresh).
 */
class EnrichIncidentsCommand extends Command
{
    protected $signature = 'incidents:enrich {--refresh : Recompute every record, not only those with empty fields}';

    protected $description = 'Fill or refresh the harm domain, related laws, policy angle and sensitivity on incident records';

    public function handle(): int
    {
        $written = IncidentEnrichment::apply((bool) $this->option('refresh'));
        $sensitive = ExternalIncident::where('sensitivity', IncidentEnrichment::SENSITIVE)->count();
        $overridden = count(IncidentEnrichment::overrides());
        $this->info("Enriched {$written} incident record(s); {$sensitive} marked sensitive; {$overridden} reviewer override(s) in ".IncidentEnrichment::OVERRIDES.'.');

        return self::SUCCESS;
    }
}
