<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: the first paid capability. A follow is a per-account, server-side
// relationship to a policy, jurisdiction or obligation (the browser reading
// list never leaves the device); the daily alert is built from it. Alert
// deliveries are recorded per user and day so the scheduled send is idempotent
// and every email can be traced to the window of changes it covered.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 20);   // policy|jurisdiction|obligation
            $table->string('subject_slug', 160);
            $table->timestamps();
            $table->unique(['user_id', 'subject_type', 'subject_slug']);
            $table->index(['subject_type', 'subject_slug']);
        });

        Schema::create('alert_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('sent_on');
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->unsignedSmallInteger('changes_count')->default(0);
            $table->unsignedSmallInteger('deadlines_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_deliveries');
        Schema::dropIfExists('follows');
    }
};
