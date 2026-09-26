<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The versions of the generated templates (P5). Files live on the local disk under templates/. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 96)->index();
            $table->unsignedInteger('version');
            $table->string('content_hash', 64);
            $table->string('dataset_version', 16);
            $table->json('files');
            $table->json('stats')->nullable();
            $table->json('preview')->nullable();
            $table->text('changelog')->nullable();
            $table->timestamp('generated_at');
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
            $table->unique(['slug', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
