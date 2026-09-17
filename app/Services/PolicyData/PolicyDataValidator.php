<?php

namespace App\Services\PolicyData;

/**
 * Validates every record in data/ against its JSON Schema, then runs the
 * cross-record checks the schema cannot express (unique slugs, taxonomy slugs,
 * jurisdiction references, verified records must carry a verification date and
 * name a reviewer who has published a declaration of interest).
 */
class PolicyDataValidator
{
    /** Names of published reviewers, collected before the records are walked. @var list<string> */
    private array $reviewerNames = [];

    public function __construct(
        private readonly PolicyDataRepository $repository,
        private readonly SchemaValidator $schema,
    ) {}

    /** @return array<string, list<string>> file => errors */
    public function run(): array
    {
        $errors = [];
        $this->reviewerNames = $this->validateReviewers($errors);
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

    /**
     * Validates the roster and returns the names of its published entries.
     *
     * @return list<string>
     */
    private function validateReviewers(array &$errors): array
    {
        $names = [];
        $slugs = [];
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
}
