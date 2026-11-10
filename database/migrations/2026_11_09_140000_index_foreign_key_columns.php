<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * PostgreSQL does not index a foreign key column on its own. These 31 had no index that
 * starts with them, so a join or an ON DELETE check on the parent scanned the child table.
 * Schema only: no row is read or changed. Columns that already lead an index are skipped,
 * so the migration is safe on a database where some were added by hand.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'jurisdictions' => ['parent_jurisdiction_id'],
        'applicability_rules' => ['obligation_id'],
        'enforcement_events' => ['policy_instrument_id', 'jurisdiction_id'],
        'procurement_rules' => ['policy_instrument_id', 'jurisdiction_id'],
        'evidence_artifacts' => ['obligation_id'],
        'source_documents' => ['jurisdiction_id'],
        'reviewer_decisions' => ['reviewer_user_id', 'contributor_submission_id'],
        'app_settings' => ['updated_by'],
        'tools' => ['updated_by'],
        'resource_downloads' => ['tool_file_id', 'user_id'],
        'record_verifications' => ['user_id'],
        'subscriptions' => ['billing_customer_id'],
        'billing_checkouts' => ['user_id'],
        'control_evidence' => ['control_id'],
        'control_framework_references' => ['control_id'],
        'job_runs' => ['user_id'],
        'channel_deliveries' => ['alert_delivery_id'],
        'transition_measures' => ['jurisdiction_id'],
        'transition_indicators' => ['jurisdiction_id'],
        'users' => ['invited_by', 'admin_role_granted_by'],
        'admin_role_permissions' => ['updated_by'],
        'implementation_measures' => ['related_policy_instrument_id', 'policy_instrument_id'],
        'social_posts' => ['approved_by'],
        'record_publications' => ['user_id'],
        'source_checks' => ['user_id'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column) && ! $this->leadsAnIndex($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($column, $this->name($table, $column)));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $this->name($table, $column))) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($this->name($table, $column)));
                }
            }
        }
    }

    private function leadsAnIndex(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'][0] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    private function name(string $table, string $column): string
    {
        return 'fkx_'.$table.'_'.$column;
    }
};
