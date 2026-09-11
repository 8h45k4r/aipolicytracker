<?php

namespace App\Services\ExternalData;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal client for the AI Incident Database GraphQL endpoint used by the live
 * sync. The endpoint only answers requests that carry the site's own Origin
 * header; we send it together with a User-Agent that names this project, keep
 * page sizes small and never fetch report texts (only metadata that AIID
 * publishes under CC BY-SA 4.0).
 */
class AiidApiClient
{
    public const ENDPOINT = 'https://incidentdatabase.ai/api/graphql';

    public const ORIGIN = 'https://incidentdatabase.ai';

    private const INCIDENT_FIELDS = 'incident_id date date_modified title description editor_notes
        AllegedDeployerOfAISystem { entity_id name } AllegedDeveloperOfAISystem { entity_id name } AllegedHarmedOrNearlyHarmedParties { entity_id name }
        implicated_systems { entity_id name } editor_similar_incidents nlp_similar_incidents { incident_id similarity }
        reports { report_number title url source_domain date_published authors language is_incident_report }';

    public function __construct(private readonly int $timeout = 60) {}

    /**
     * One page of incidents, newest first. When $modifiedAfter is given only
     * records changed after that instant are returned (incremental sync).
     *
     * @return array<int, array<string, mixed>>
     */
    public function incidents(int $limit = 100, int $skip = 0, ?string $modifiedAfter = null): array
    {
        $filter = $modifiedAfter ? sprintf('filter: { date_modified: { GT: "%s" } }, ', $modifiedAfter) : '';
        $query = sprintf('{ incidents(%ssort: { incident_id: DESC }, pagination: { limit: %d, skip: %d }) { %s } }', $filter, $limit, $skip, self::INCIDENT_FIELDS);

        return $this->query($query)['incidents'] ?? [];
    }

    /**
     * Published classifications (MIT and CSETv1 namespaces) for a set of incidents,
     * keyed by incident id then namespace, each a map short_name => decoded value.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function classifications(array $ids): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($ids)), 100) as $chunk) {
            $query = sprintf('{ classifications(filter: { incidents: { IN: [%s] }, namespace: { IN: ["MIT", "CSETv1"] }, publish: { EQ: true } }, pagination: { limit: 500 }) { namespace incidents { incident_id } attributes { short_name value_json } } }', implode(',', array_map('intval', $chunk)));
            foreach ($this->query($query)['classifications'] ?? [] as $c) {
                $attrs = [];
                foreach ($c['attributes'] ?? [] as $a) {
                    $attrs[$a['short_name']] = json_decode((string) $a['value_json'], true);
                }
                foreach ($c['incidents'] ?? [] as $i) {
                    $out[(int) $i['incident_id']][$c['namespace']] = $attrs;
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function query(string $query): array
    {
        $response = $this->http()->post(self::ENDPOINT, ['query' => $query]);
        if (! $response->ok()) {
            throw new RuntimeException('AIID API responded '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }
        $json = $response->json();
        if (! empty($json['errors'])) {
            throw new RuntimeException('AIID API error: '.($json['errors'][0]['message'] ?? 'unknown'));
        }

        return $json['data'] ?? [];
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeout)->retry(2, 2000, throw: false)->withHeaders([
            'Origin' => self::ORIGIN,
            'Referer' => self::ORIGIN.'/',
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (compatible; aipolicytracker.org sync; +https://aipolicytracker.org/open-data)',
        ])->acceptJson();
    }
}
