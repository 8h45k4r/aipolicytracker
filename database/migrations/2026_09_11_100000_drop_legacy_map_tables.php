<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: the legacy map site (React) and its admin CRUD were removed; the public site
// reads only the structured policy-intelligence tables and all 68 legacy instruments
// were bridged into data/ (PR #22). Dropping the tables ends the second source of truth.
// down() recreates the schema so a backup can be restored if ever needed.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['book_marks', 'a_i_policy_activity_logs', 'thumbnails', 'news_future_images', 'news', 'ai_policy_trackers', 'statuses', 'countries', 'nav_bars', 'contributing_orgs'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        Schema::create('countries', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('symbol'); $t->string('name'); $t->boolean('status')->nullable()->default(true); $t->softDeletes(); $t->timestamps(); });
        Schema::create('statuses', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('name'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('ai_policy_trackers', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('country_id'); $t->uuid('status_id'); $t->string('ai_policy_name')->nullable(); $t->string('governing_body')->nullable(); $t->string('announcement_year')->nullable(); $t->string('whitepaper_document_link')->nullable(); $t->string('technology_partners')->nullable(); $t->string('governance_structure')->nullable(); $t->string('main_motivation')->nullable(); $t->longText('description')->nullable(); $t->string('gov_ai_index')->default('policy'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('news', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('policy_tracker_id')->nullable(); $t->uuid('status_id')->nullable(); $t->string('title'); $t->longText('description')->nullable(); $t->date('upload_date'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('thumbnails', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('news_id'); $t->string('type'); $t->string('name'); $t->string('path'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('news_future_images', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('news_id'); $t->string('type'); $t->string('name'); $t->string('path'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('a_i_policy_activity_logs', function (Blueprint $t) { $t->uuid('id')->primary(); $t->foreignId('user_id')->nullable()->index(); $t->uuid('ai_policy_tracker_id'); $t->text('activity_name'); $t->longText('description')->nullable(); $t->timestamps(); });
        Schema::create('book_marks', function (Blueprint $t) { $t->uuid('id')->primary(); $t->unsignedBigInteger('user_id'); $t->uuid('ai_policy_tracker_id'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('nav_bars', function (Blueprint $t) { $t->uuid('id')->primary(); $t->unsignedBigInteger('user_id'); $t->string('name'); $t->string('file_path'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('contributing_orgs', function (Blueprint $t) { $t->uuid('id')->primary(); $t->unsignedBigInteger('user_id'); $t->string('name'); $t->string('file_path'); $t->string('url')->nullable(); $t->softDeletes(); $t->timestamps(); });
    }
};
