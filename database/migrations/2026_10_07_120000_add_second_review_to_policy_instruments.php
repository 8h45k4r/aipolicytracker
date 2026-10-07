<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: a record verified by one person is only as good as that person. An independent
// second check by a different named reviewer, and the agreement between the two across a
// sample of records, is what lets a reader judge the verification process rather than
// trust it. The check is recorded in data/ (`second_review` on the policy YAML, validated
// by policy:validate) and imported here as JSON so /methodology can compute agreement
// statistics and record pages can say "checked by" without reading YAML per request.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policy_instruments', function (Blueprint $table) {
            $table->json('second_review')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('policy_instruments', function (Blueprint $table) {
            $table->dropColumn('second_review');
        });
    }
};
