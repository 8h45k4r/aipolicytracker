<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * An admin's publish decision for a reviewable record, kept across imports the way
 * record_verifications keeps review decisions. A takedown (published = false) is
 * honoured by every later import until an admin republishes; the date the record
 * first went public is kept here so republishing restores it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_publications', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 32);
            $table->string('record_slug', 160);
            $table->boolean('published');
            $table->timestamp('first_published_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['record_type', 'record_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_publications');
    }
};
