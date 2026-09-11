<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: the admin funnel (tool page view → download click → sign-up → download) had no
// page-view side without third-party analytics. This stores daily counts per path only:
// no cookies, no IPs, no user ids, so it needs no consent and cannot identify anyone.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('path', 191);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
            $table->unique(['day', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
