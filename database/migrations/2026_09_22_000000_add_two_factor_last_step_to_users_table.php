<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: a time-based code is valid for a 30-second step plus one step either side,
// so a code seen once (over a shoulder, in a screenshot, on a compromised page)
// could be replayed for up to ninety seconds. Recording the step of the last
// accepted code lets the challenge refuse that step, and every earlier one, a
// second time. RFC 6238 §5.2 asks for exactly this.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_last_step');
        });
    }
};
