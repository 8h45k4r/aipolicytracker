<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions for user management.
 *
 * On users: when the account last signed in (the question behind most access reviews:
 * "does this person still use their access?"), and who invited it and when, so an
 * invitation that was never accepted can be told apart from a registration.
 *
 * admin_role_permissions holds an owner's edits to what a role may do. A role with no
 * row here has its defaults from App\Enums\AdminRole, so an empty table changes nothing
 * and deleting a row is "reset to defaults". Owner-only capabilities are refused when a
 * row is written and ignored when one is read, so this table cannot become a route to
 * settings, billing or user management.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->index();
            $table->timestamp('invited_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 16)->unique();
            $table->json('capabilities');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_permissions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn(['last_login_at', 'invited_at']);
        });
    }
};
