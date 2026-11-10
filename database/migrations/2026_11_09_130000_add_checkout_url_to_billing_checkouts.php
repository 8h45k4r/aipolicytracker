<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The provider's checkout address, kept with the attempt so a second click (another tab,
 * a double submit) reopens the same session instead of creating a second one that could
 * also be paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_checkouts', function (Blueprint $table) {
            $table->string('checkout_url', 2048)->nullable()->after('provider_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('billing_checkouts', function (Blueprint $table) {
            $table->dropColumn('checkout_url');
        });
    }
};
