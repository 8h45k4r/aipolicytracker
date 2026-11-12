<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Headlines from official sources' own feeds (config/news.php): title, link, date, publisher. No article text. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);          // a key of config('news.sources')
            $table->string('external_id', 255);    // the feed's guid/id, else the link
            $table->string('title', 500);
            $table->string('url', 500);            // the publisher's page
            $table->string('jurisdiction_slug', 80)->nullable();
            $table->json('topics');
            $table->timestamp('published_at');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_id']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
