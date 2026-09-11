<?php

namespace App\Services\ExternalData;

use Illuminate\Support\Facades\File;

/**
 * Read model for third-party datasets kept as reviewed JSON under data/external/.
 * Files are refreshed by the external:sync-* commands (weekly workflow) and
 * committed through pull requests, so every change is visible and licence
 * attribution travels with the data.
 */
class ExternalDataset
{
    public const AIID = 'data/external/aiid_summary.json';

    public const MIT_RISK = 'data/external/mit_ai_risk_domains.json';

    public const AIID_INCIDENTS = 'data/external/aiid_incidents.json';

    public const MIT_RISKS = 'data/external/mit_risks.json';

    /** @return array<string, mixed> */
    public function aiid(): array
    {
        return $this->read(self::AIID);
    }

    /** @return array<string, mixed> */
    public function mitRisk(): array
    {
        return $this->read(self::MIT_RISK);
    }

    /** @return array<string, mixed> Metadata of the row-level MIT risk file (papers list, counts). */
    public function mitRisksMeta(): array
    {
        $all = $this->read(self::MIT_RISKS);
        unset($all['risks']);

        return $all;
    }

    /** @return array<string, mixed>|null */
    public function mitDomain(string $id): ?array
    {
        foreach ($this->mitRisk()['domains'] ?? [] as $domain) {
            if ((string) $domain['id'] === $id) {
                return $domain;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function read(string $relative): array
    {
        $path = base_path($relative);
        if (! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
