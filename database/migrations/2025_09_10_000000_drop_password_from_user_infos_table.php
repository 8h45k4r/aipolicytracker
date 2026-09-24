<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why: earlier versions stored the registration password in plain text on user_infos.
 * This column is never read by the application; drop it and its data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_infos', 'password')) {
            Schema::table('user_infos', function (Blueprint $table) {
                $table->dropColumn('password');
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_infos', function (Blueprint $table) {
            $table->string('password')->nullable()->default(null);
        });
    }
};
