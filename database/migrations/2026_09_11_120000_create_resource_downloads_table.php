<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: free templates on /guides are gated behind a free account. Each download is
// recorded (who, which resource and file, which version, when, and the terms acceptance)
// so the admin can see demand and so the licence acceptance is evidenced. Consent to
// updates is stored on the user separately from terms acceptance and is never pre-ticked.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource_slug', 120)->index();
            $table->string('file_name', 160);
            $table->string('version', 16);
            $table->timestamp('terms_accepted_at');
            $table->string('ip_hash', 64)->nullable();   // sha256 of the IP; security metadata only
            $table->string('user_agent', 255)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->timestamp('downloaded_at')->nullable()->index(); // set when the file is actually served
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('remember_token');
            $table->timestamp('marketing_consent_at')->nullable()->after('terms_accepted_at');
            $table->string('organization_name')->nullable()->after('marketing_consent_at');
            $table->string('signup_source', 64)->nullable()->after('organization_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_downloads');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'marketing_consent_at', 'organization_name', 'signup_source']);
        });
    }
};
