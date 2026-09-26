<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Date and status changes to recorded deadlines, so a page can show "originally X, now Y" (P7). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deadline_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_instrument_id')->constrained('policy_instruments')->cascadeOnDelete();
            $table->string('deadline_key', 128);
            $table->string('title');
            $table->date('from_due_on')->nullable();
            $table->date('to_due_on')->nullable();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->string('from_label')->nullable();
            $table->string('to_label')->nullable();
            $table->timestamp('changed_at');
            $table->string('source', 32)->default('import');
            $table->timestamps();
            $table->index(['policy_instrument_id', 'deadline_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deadline_revisions');
    }
};
