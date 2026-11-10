<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per licence, team or custom-coverage enquiry sent from /pricing or /open-data.
// Its own table rather than contributor_submissions: those are corrections to the
// records, worked by reviewers and published to the corrections log, while an enquiry
// is a sales conversation that must never enter the editorial queue. The row is the
// enquiry: the confirmation on the page and the mail to the contact address both
// follow its insert, so a lost mail never loses the enquiry.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licence_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->index();
            $table->string('organization', 160);
            $table->string('tier', 16)->index(); // builder|team|commercial|unsure
            $table->text('use_case');
            $table->text('coverage_request')->nullable();
            $table->string('source', 16)->nullable(); // pricing|open-data
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_enquiries');
    }
};
