<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL does not index a foreign key column on its own. Every listed column is read
 * by a relation load or a cascading delete, so each gets an index unless one already
 * leads with it.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'policy_instruments' => ['jurisdiction_id'],
        'obligations' => ['policy_instrument_id', 'policy_section_id'],
        'change_events' => ['jurisdiction_id', 'policy_instrument_id'],
        'deadlines' => ['policy_instrument_id', 'obligation_id'],
        'policy_sections' => ['policy_instrument_id'],
        'policy_versions' => ['policy_instrument_id'],
        'source_documents' => ['policy_instrument_id'],
        'applicability_rules' => ['policy_instrument_id'],
        'framework_mappings' => ['obligation_id'],
        'control_obligation' => ['obligation_id'],
        'channel_deliveries' => ['alert_channel_id'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $leading = collect(Schema::getIndexes($table))->map(fn ($i) => $i['columns'][0] ?? null)->filter()->all();
            $missing = array_diff($columns, $leading);
            if ($missing !== []) {
                Schema::table($table, function (Blueprint $t) use ($table, $missing) {
                    foreach ($missing as $column) {
                        $t->index($column, $table.'_'.$column.'_fk_index');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasIndex($table, $table.'_'.$column.'_fk_index')) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($table.'_'.$column.'_fk_index'));
                }
            }
        }
    }
};
