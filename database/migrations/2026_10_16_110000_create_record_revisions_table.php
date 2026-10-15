<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A record's own history, field by field: what changed, from what, to what, and when
// the site started saying it. Written by the importer when a tracked field differs from
// what the database held, so a reader can see how a record has moved without reading
// git. History starts when this table was added; earlier edits are in the repository.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 16); // policy | obligation | change | control
            $table->string('record_slug', 160);
            $table->string('aipt_id', 24)->nullable()->index();
            $table->unsignedInteger('content_version')->nullable();
            $table->text('change_summary')->nullable();
            $table->json('fields'); // [{field, label, from, to}]
            $table->string('source', 16)->default('import');
            $table->timestamp('changed_at')->index();
            $table->timestamps();
            $table->index(['record_type', 'record_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_revisions');
    }
};
