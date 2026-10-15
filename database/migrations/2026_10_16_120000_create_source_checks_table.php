<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The change desk's log: each time someone opens an instrument's official source to look
// for changes, what they found. It is what lets a record say "source checked 3 days ago,
// no change" rather than only when it was last verified, and it is never touched by the
// import, so it survives every rebuild of the read model.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_checks', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 16)->default('policy');
            $table->string('record_slug', 160);
            $table->string('outcome', 24); // no_change | change_logged | source_unreachable
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
            $table->index(['record_type', 'record_slug', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_checks');
    }
};
