<?php

namespace App\Services\PolicyData;

/**
 * Validates every record in data/ against its JSON Schema, then runs the
 * cross-record checks the schema cannot express (unique slugs, taxonomy slugs,
 * jurisdiction references, verified records must carry a verification date).
 */
class PolicyDataValidator
{
    public function __construct(
        private readonly PolicyDataRepository $repository,
        private readonly SchemaValidator $schema,
    ) {
    }

    /** @return array<string, list<string>> file => errors */
    public function run(): array
    {
        $errors = [];
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

        $policySlugs = [];
        $obligationSlugs = [];
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
                $this->checkVerification($obligation, $file, "$.obligations[{$i}]", $errors);
            }
            foreach ($record['deadlines'] ?? [] as $i => $deadline) {
                if (($deadline['date_precision'] ?? 'exact') !== 'tbd' && empty($deadline['due_on'])) {
                    $errors[$file][] = "$.deadlines[{$i}]: due_on is required unless date_precision is \"tbd\"";
                }
            }
        }

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

    private function checkVerification(array $record, string $file, string $path, array &$errors): void
    {
        $status = $record['review_status'] ?? null;
        if ($status === 'verified' && empty($record['last_verified_at'])) {
            $errors[$file][] = "{$path}: review_status is \"verified\" but last_verified_at is empty";
        }
        if ($status === 'verified' && empty($record['reviewed_by'])) {
            $errors[$file][] = "{$path}: review_status is \"verified\" but reviewed_by is empty";
        }
        if (! empty($record['last_verified_at']) && $status !== 'verified') {
            $errors[$file][] = "{$path}: last_verified_at is set but review_status is not \"verified\"";
        }
    }
}
