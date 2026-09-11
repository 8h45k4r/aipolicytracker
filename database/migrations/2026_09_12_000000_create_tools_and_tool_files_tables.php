<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: the free tools on /guides were defined in config/resources.php, which meant every new
// template needed a code change. Moving them to tables lets the admin create, edit, upload
// files for and archive tools without a deploy. Files stay on the private local disk and are
// only served through signed, owner-bound links, exactly as before.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('type', 24)->index();               // guide|template|checklist|register
            $table->string('short', 300);
            $table->text('purpose')->nullable();
            $table->json('fields')->nullable();                // [[name, description], ...]
            $table->json('instructions')->nullable();          // [string, ...]
            $table->json('frameworks')->nullable();            // slugs from config('resources.frameworks')
            $table->json('topics')->nullable();                // slugs from config('resources.topics')
            $table->json('related_guides')->nullable();        // guide slugs from config/content.php
            $table->json('related_policies')->nullable();      // policy slugs
            $table->string('next_slug', 120)->nullable();
            $table->string('version', 16)->default('1.0');
            $table->date('updated_on')->nullable();
            $table->boolean('featured')->default(false);
            $table->string('status', 16)->default('draft')->index(); // draft|published|archived
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('tool_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->string('file_name', 160);                  // name shown and used in URLs
            $table->string('label', 40);                        // XLSX, CSV, Markdown, PDF ...
            $table->string('disk_path', 255);                   // path on the private local disk
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();         // sha256 of the stored file
            $table->string('version', 16)->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tool_id', 'file_name']);
        });
        Schema::table('resource_downloads', function (Blueprint $table) {
            $table->foreignId('tool_file_id')->nullable()->after('resource_slug')->constrained('tool_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resource_downloads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tool_file_id');
        });
        Schema::dropIfExists('tool_files');
        Schema::dropIfExists('tools');
    }
};
