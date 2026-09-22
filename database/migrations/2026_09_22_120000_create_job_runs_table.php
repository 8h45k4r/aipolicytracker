<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: the digest, the alerts, the syncs and the imports ran from GitHub Actions, the
// container or an operator's terminal, and nothing on the platform could say when a job
// last ran or whether it finished. One row per run, whoever started it, so the admin
// dashboard can show the timetable and its last outcome, and an operator can run any
// job from the browser instead of the terminal.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job', 64)->index();
            $table->string('trigger', 16);              // schedule|admin|cron|console
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->smallInteger('exit_code')->nullable();
            $table->text('output')->nullable();
            $table->timestamps();
            $table->index(['job', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_runs');
    }
};
