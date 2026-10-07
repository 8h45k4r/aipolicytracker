<?php

namespace App\Services\PolicyData;

use App\Models\EnforcementEvent;
use App\Services\Verification\SecondReview;

/**
 * Validates every record in data/ against its JSON Schema, then runs the
 * cross-record checks the schema cannot express (unique slugs, taxonomy slugs,
 * jurisdiction references, verified records must carry a verification date and
 * name a reviewer who has published a declaration of interest; a second review
 * must be by a different published reviewer).
 */
class PolicyDataValidator
{
    /** Names of published reviewers, collected before the records are walked. @var list<string> */
    private array $reviewerNames = [];

    /** Published reviewer name => roster slug, for the independence check on second reviews. @var array<string, string> */
    private array $reviewerSlugs = [];

    public function __construct(
        private readonly PolicyDataRepository $repository,
        private readonly SchemaValidator $schema,
    ) {}

    /** @return array<string, list<string>> file => errors */
    public function run(): array
    {
        $errors = [];
        $this->reviewerNames = $this->validateReviewers($errors);
        $this->validateTransition($errors);
        $taxonomies = $this->repository->taxonomies();
        foreach ($this->schema->validate($taxonomies, 'taxonomy.schema.json') as $e) {
            $errors['taxonomies/terms.yaml'][] = $e;
        }
        $termSlugs = [];
        foreach ($taxonomies as $taxonomy => $terms) {
            foreach ($terms as $term) {
                $termSlugs[$taxonomy][] = $term['slug'] ?? '';
            }
        }

        $jurisdictionSlugs = [];
        foreach ($this->repository->jurisdictions() as $file => $record) {
            foreach ($this->schema->validate($record, 'jurisdiction.schema.json') as $e) {
                $errors[$file][] = $e;
            }
            $slug = $record['slug'] ?? null;
            if ($slug) {
                if (isset($jurisdictionSlugs[$slug])) {
                    $errors[$file][] = "duplicate jurisdiction slug \"{$slug}\" (also in {$jurisdictionSlugs[$slug]})";
                }
                $jurisdictionSlugs[$slug] = $file;
            }
            $this->checkVerification($record, $file, '$', $errors);
        }

        // Controls: schema, slug equals file name, evidence types from the taxonomy,
        // related controls resolve. Collected first so an obligation's reference to
        // one can be checked below.
        $controlSlugs = [];
        $controls = $this->repository->controls();
        foreach ($controls as $file => $record) {
            foreach ($this->schema->validate($record, 'control.schema.json') as $e) {
                $errors[$file][] = $e;
            }
            $slug = $record['slug'] ?? null;
            if ($slug) {
                if (isset($controlSlugs[$slug])) {
                    $errors[$file][] = "duplicate control slug \"{$slug}\" (also in {$controlSlugs[$slug]})";
                }
                if (basename($file, '.yaml') !== $slug) {
                    $errors[$file][] = "control slug \"{$slug}\" must match the file name";
                }
                $controlSlugs[$slug] = $file;
            }
            foreach ($record['evidence'] ?? [] as $i => $evidence) {
                if (! in_array($evidence['type'] ?? '', $termSlugs['evidence_type'] ?? [], true)) {
                    $errors[$file][] = "$.evidence[{$i}].type: unknown evidence_type term \"".($evidence['type'] ?? '').'"';
                }
            }
            $this->checkVerification($record, $file, '$', $errors);
        }
        foreach ($controls as $file => $record) {
            foreach ($record['related_controls'] ?? [] as $related) {
                if (! isset($controlSlugs[$related])) {
                    $errors[$file][] = "$.related_controls: unknown control slug \"{$related}\"";
                }
            }
        }

        $policySlugs = [];
        $obligationSlugs = [];
        $enforcementSlugs = [];
        $policies = $this->repository->policies();
        foreach ($policies as $file => $record) {
            foreach ($this->schema->validate($record, 'policy.schema.json') as $e) {
                $errors[$file][] = $e;
            }
            $slug = $record['slug'] ?? null;
            if ($slug) {
                if (isset($policySlugs[$slug])) {
                    $errors[$file][] = "duplicate policy slug \"{$slug}\" (also in {$policySlugs[$slug]})";
                }
                $policySlugs[$slug] = $file;
            }
            if (isset($record['jurisdiction']) && ! isset($jurisdictionSlugs[$record['jurisdiction']])) {
                $errors[$file][] = "unknown jurisdiction \"{$record['jurisdiction']}\"";
            }
            foreach (['actors' => 'actor', 'ai_system_types' => 'ai_system_type', 'sectors' => 'sector', 'risk_categories' => 'risk_category', 'use_cases' => 'use_case'] as $field => $taxonomy) {
                foreach ($record[$field] ?? [] as $value) {
                    if (! in_array($value, $termSlugs[$taxonomy] ?? [], true)) {
                        $errors[$file][] = "$.{$field}: unknown {$taxonomy} term \"{$value}\"";
                    }
                }
            }
            $this->checkVerification($record, $file, '$', $errors);
            foreach (SecondReview::errors($record, $this->reviewerSlugs) as $e) {
                $errors[$file][] = $e;
            }
            foreach ($record['obligations'] ?? [] as $i => $obligation) {
                $oslug = $obligation['slug'] ?? '';
                if (isset($obligationSlugs[$oslug])) {
                    $errors[$file][] = "$.obligations[{$i}]: duplicate obligation slug \"{$oslug}\"";
                }
                $obligationSlugs[$oslug] = $file;
                if (isset($obligation['category']) && ! in_array($obligation['category'], $termSlugs['obligation_category'] ?? [], true)) {
                    $errors[$file][] = "$.obligations[{$i}].category: unknown obligation_category \"{$obligation['category']}\"";
                }
                foreach (['actors' => 'actor', 'sectors' => 'sector', 'use_cases' => 'use_case'] as $field => $taxonomy) {
                    foreach ($obligation[$field] ?? [] as $value) {
                        if (! in_array($value, $termSlugs[$taxonomy] ?? [], true)) {
                            $errors[$file][] = "$.obligations[{$i}].{$field}: unknown {$taxonomy} term \"{$value}\"";
                        }
                    }
                }
                foreach ($obligation['controls'] ?? [] as $k => $link) {
                    if (! isset($controlSlugs[$link['control'] ?? ''])) {
                        $errors[$file][] = "$.obligations[{$i}].controls[{$k}]: unknown control \"".($link['control'] ?? '').'"';
                    }
                }
                $this->checkVerification($obligation, $file, "$.obligations[{$i}]", $errors);
            }
            foreach ($record['deadlines'] ?? [] as $i => $deadline) {
                if (($deadline['date_precision'] ?? 'exact') !== 'tbd' && empty($deadline['due_on'])) {
                    $errors[$file][] = "$.deadlines[{$i}]: due_on is required unless date_precision is \"tbd\"";
                }
            }
            $this->checkEnforcementEvents($record, $file, $jurisdictionSlugs, $enforcementSlugs, $errors);
        }
        $this->validateImplementation($policySlugs, $errors);

        // related_policies must resolve.
        foreach ($policies as $file => $record) {
            foreach ($record['related_policies'] ?? [] as $related) {
                if (! isset($policySlugs[$related])) {
                    $errors[$file][] = "$.related_policies: unknown policy slug \"{$related}\"";
                }
            }
        }
        foreach ($this->repository->jurisdictions() as $file => $record) {
            foreach ($record['related_jurisdictions'] ?? [] as $related) {
                if (! isset($jurisdictionSlugs[$related])) {
                    $errors[$file][] = "$.related_jurisdictions: unknown jurisdiction slug \"{$related}\"";
                }
            }
        }

        $changeSlugs = [];
        foreach ($this->repository->changeFiles() as $file => $contents) {
            foreach ($this->schema->validate($contents, 'change.schema.json') as $e) {
                $errors[$file][] = $e;
            }
            foreach ($contents['changes'] ?? [] as $i => $change) {
                $slug = $change['slug'] ?? '';
                if (isset($changeSlugs[$slug])) {
                    $errors[$file][] = "$.changes[{$i}]: duplicate change slug \"{$slug}\"";
                }
                $changeSlugs[$slug] = $file;
                if (isset($change['jurisdiction']) && ! isset($jurisdictionSlugs[$change['jurisdiction']])) {
                    $errors[$file][] = "$.changes[{$i}]: unknown jurisdiction \"{$change['jurisdiction']}\"";
                }
                if (! empty($change['policy']) && ! isset($policySlugs[$change['policy']])) {
                    $errors[$file][] = "$.changes[{$i}]: unknown policy \"{$change['policy']}\"";
                }
                $this->checkVerification($change, $file, "$.changes[{$i}]", $errors);
            }
        }

        return $errors;
    }

    /**
     * Validates the roster and returns the names of its published entries.
     *
     * @return list<string>
     */
    private function validateReviewers(array &$errors): array
    {
        $names = [];
        $slugs = [];
        $this->reviewerSlugs = [];
        foreach ($this->repository->reviewers() as $file => $record) {
            foreach ($this->schema->validate($record, 'reviewer.schema.json') as $e) {
                $errors[$file][] = $e;
            }
            $slug = $record['slug'] ?? null;
            if ($slug !== null) {
                if (isset($slugs[$slug])) {
                    $errors[$file][] = "duplicate reviewer slug \"{$slug}\" (also in {$slugs[$slug]})";
                }
                $slugs[$slug] = $file;
            }
            if (($record['published'] ?? true) && isset($record['name'])) {
                $names[] = (string) $record['name'];
                $this->reviewerSlugs[(string) $record['name']] = (string) ($slug ?? '');
            }
        }

        return $names;
    }

    private function checkVerification(array $record, string $file, string $path, array &$errors): void
    {
        $status = $record['review_status'] ?? null;
        if ($status === 'verified' && empty($record['last_verified_at'])) {
            $errors[$file][] = "{$path}: review_status is \"verified\" but last_verified_at is empty";
        }
        if ($status === 'verified' && empty($record['reviewed_by'])) {
            $errors[$file][] = "{$path}: review_status is \"verified\" but reviewed_by is empty";
        }
        // A verification is only worth something if the person who made it is named and has
        // published what they are interested in. Without this, "verified by" is an unchecked
        // string and the roster is decoration.
        if ($status === 'verified' && ! empty($record['reviewed_by']) && ! in_array($record['reviewed_by'], $this->reviewerNames, true)) {
            $errors[$file][] = "{$path}: reviewed_by \"{$record['reviewed_by']}\" is not a published reviewer; add them to data/reviewers with a declaration of interest";
        }
        if (! empty($record['last_verified_at']) && $status !== 'verified') {
            $errors[$file][] = "{$path}: last_verified_at is set but review_status is not \"verified\"";
        }
    }

    /** Transition measures and indicators: schema, unique slugs equal to file names, known jurisdictions. */
    private function validateTransition(array &$errors): void
    {
        $jurisdictions = array_map(fn ($r) => $r['slug'] ?? null, $this->repository->jurisdictions());
        $seen = [];
        foreach ([['transitionMeasures', 'transition-measure.schema.json', true], ['transitionIndicators', 'transition-indicator.schema.json', false]] as [$method, $schema, $jurisdictionRequired]) {
            foreach ($this->repository->{$method}() as $file => $record) {
                foreach ($this->schema->validate($record, $schema) as $e) {
                    $errors[$file][] = $e;
                }
                $slug = $record['slug'] ?? null;
                if ($slug && pathinfo($file, PATHINFO_FILENAME) !== $slug) {
                    $errors[$file][] = "$.slug: must equal the file name ({$slug})";
                }
                if ($slug && isset($seen[$slug])) {
                    $errors[$file][] = "$.slug: duplicate slug \"{$slug}\" (also in {$seen[$slug]})";
                }
                $seen[$slug ?? $file] = $file;
                $j = $record['jurisdiction'] ?? null;
                if (($jurisdictionRequired || $j !== null) && ! in_array($j, $jurisdictions, true)) {
                    $errors[$file][] = "$.jurisdiction: unknown jurisdiction slug \"{$j}\"";
                }
                if (($record['review_status'] ?? null) === 'verified' && empty($record['official_source_url'])) {
                    $errors[$file][] = '$.review_status: a verified record must carry official_source_url';
                }
            }
        }
    }

    /**
     * Enforcement events inside a policy record: unique slugs (recorded or derived), a
     * known jurisdiction override, an amount only with its currency, and the same
     * verification rule as every other record.
     *
     * @param  array<string, string>  $jurisdictionSlugs
     * @param  array<string, string>  $seen  slug => file, shared across policies
     */
    private function checkEnforcementEvents(array $record, string $file, array $jurisdictionSlugs, array &$seen, array &$errors): void
    {
        foreach ($record['enforcement_events'] ?? [] as $i => $event) {
            if (! is_array($event)) {
                continue;
            }
            $slug = EnforcementEvent::slugFor((string) ($record['slug'] ?? ''), $event);
            if (isset($seen[$slug])) {
                $errors[$file][] = "$.enforcement_events[{$i}]: duplicate event slug \"{$slug}\" (also in {$seen[$slug]})";
            }
            $seen[$slug] = $file;
            if (! empty($event['jurisdiction']) && ! isset($jurisdictionSlugs[$event['jurisdiction']])) {
                $errors[$file][] = "$.enforcement_events[{$i}].jurisdiction: unknown jurisdiction \"{$event['jurisdiction']}\"";
            }
            if (isset($event['amount']) && $event['amount'] !== null && empty($event['currency'])) {
                $errors[$file][] = "$.enforcement_events[{$i}]: an amount needs its currency";
            }
            $this->checkVerification($event, $file, "$.enforcement_events[{$i}]", $errors);
        }
    }

    /**
     * Implementation measures (data/implementation): schema, slug equals the file name and
     * is unique, instrument and related_policy resolve, a year-precision date is 1 January,
     * a declared adopted or published status carries its date unless the record is a draft,
     * OJ citation dates only on harmonised standards, verified records cite their source.
     *
     * @param  array<string, string>  $policySlugs
     */
    private function validateImplementation(array $policySlugs, array &$errors): void
    {
        $seen = [];
        foreach ($this->repository->implementationMeasures() as $file => $record) {
            foreach ($this->schema->validate($record, 'implementation-measure.schema.json') as $e) {
                $errors[$file][] = $e;
            }
            $slug = $record['slug'] ?? null;
            if ($slug && pathinfo($file, PATHINFO_FILENAME) !== $slug) {
                $errors[$file][] = "$.slug: must equal the file name ({$slug})";
            }
            if ($slug && isset($seen[$slug])) {
                $errors[$file][] = "$.slug: duplicate slug \"{$slug}\" (also in {$seen[$slug]})";
            }
            $seen[$slug ?? $file] = $file;
            foreach (['instrument', 'related_policy'] as $field) {
                if (! empty($record[$field]) && ! isset($policySlugs[$record[$field]])) {
                    $errors[$file][] = "$.{$field}: unknown policy slug \"{$record[$field]}\"";
                }
            }
            if (! in_array($record['kind'] ?? null, ['harmonised_standard', 'iso_work_item', 'standardisation_request'], true) && empty($record['instrument'])) {
                $errors[$file][] = '$.instrument: a measure that is not a standard must name the instrument it implements';
            }
            if (($record['published_on_precision'] ?? 'exact') === 'year' && ! empty($record['published_on']) && ! str_ends_with((string) $record['published_on'], '-01-01')) {
                $errors[$file][] = '$.published_on: with published_on_precision "year" the date must be 1 January of that year';
            }
            if (($record['published_on_precision'] ?? 'exact') === 'month' && ! empty($record['published_on']) && ! str_ends_with((string) $record['published_on'], '-01')) {
                $errors[$file][] = '$.published_on: with published_on_precision "month" the date must be the first of that month';
            }
            $draft = ($record['review_status'] ?? null) === 'draft';
            if (! $draft && ($record['status'] ?? null) === 'published' && empty($record['published_on'])) {
                $errors[$file][] = '$.published_on: a measure declared "published" needs its publication date';
            }
            if (! $draft && ($record['status'] ?? null) === 'adopted' && empty($record['adopted_on'])) {
                $errors[$file][] = '$.adopted_on: a measure declared "adopted" needs its adoption date';
            }
            if (! $draft && empty($record['official_source_url'])) {
                $errors[$file][] = '$.official_source_url: only a draft (review_status "draft") may omit its official source';
            }
            if (($record['kind'] ?? null) !== 'harmonised_standard' && (! empty($record['oj_citation_expected_on']) || ! empty($record['oj_citation_on']))) {
                $errors[$file][] = '$.oj_citation_on: only a harmonised standard is cited in the Official Journal';
            }
            if (($record['review_status'] ?? null) === 'verified' && empty($record['official_source_url'])) {
                $errors[$file][] = '$.review_status: a verified record must carry official_source_url';
            }
            $this->checkVerification($record, $file, '$', $errors);
        }
    }
}
