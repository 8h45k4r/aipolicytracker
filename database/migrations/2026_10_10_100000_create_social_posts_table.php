<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per change event per network: the post as it will read, and what
// happened when it was sent. The unique key is what stops a change being
// posted twice, whichever door (schedule, admin button) the run came through.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_event_id')->constrained()->cascadeOnDelete();
            $table->string('network', 16)->default('x');
            $table->string('status', 16)->default('queued')->index(); // draft|queued|posted|failed|skipped
            $table->text('text');
            $table->string('external_id', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['change_event_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
