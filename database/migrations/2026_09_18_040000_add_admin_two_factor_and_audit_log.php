<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: the admin area publishes and unpublishes records, decides submissions, stores API
// keys and can export every account. Until now a single password stood in front of all of
// that, and nothing recorded who did what. Two columns hold an admin's authenticator
// secret and their one-time recovery codes (both encrypted at rest through model casts);
// the audit table records every state-changing admin request so a change can be traced to
// a person, a route and a time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_email', 190)->nullable();   // kept even if the account is later deleted
            $table->string('method', 8);
            $table->string('route_name', 120)->nullable();
            $table->string('path', 512);
            $table->json('route_params')->nullable();         // identifiers only, never request bodies
            $table->unsignedSmallInteger('status');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
