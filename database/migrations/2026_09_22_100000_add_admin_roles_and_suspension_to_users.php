<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrative access was a single environment variable holding a list of email addresses,
 * so it was binary and could only be changed by editing deploy/.env on the host and restarting
 * the container. That is fine for one owner and unusable for a team.
 *
 * These columns add the grantable roles. Ownership deliberately stays in the environment and
 * is never stored here: a mistake in this table, or write access to it, must not be able to
 * create an owner, remove the last one, or lock everybody out of a running deployment.
 *
 * Every column is nullable so existing rows remain valid and the environment list stays
 * authoritative for the accounts that already had access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // One of App\Enums\AdminRole, or null for an account with no admin access.
            $table->string('admin_role', 16)->nullable()->index();

            // Who granted it and when. Without this a role change is invisible once the audit
            // log rotates, and "who gave this person access" has no answer.
            $table->foreignId('admin_role_granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admin_role_granted_at')->nullable();

            // Suspension blocks sign-in outright, rather than only removing admin access, so a
            // compromised or departed account can be stopped without deleting its history.
            $table->timestamp('suspended_at')->nullable()->index();
            $table->string('suspended_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_role_granted_by');
            $table->dropColumn(['admin_role', 'admin_role_granted_at', 'suspended_at', 'suspended_reason']);
        });
    }
};
