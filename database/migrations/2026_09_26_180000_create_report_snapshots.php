<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Frozen quarterly figures for /state-of-ai-regulation (P10). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('quarter', 7);
            $table->string('version', 8);
            $table->json('data');
            $table->timestamp('frozen_at');
            $table->timestamps();
            $table->unique(['quarter', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
