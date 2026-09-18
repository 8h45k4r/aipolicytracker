<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: the registration form has never asked for a phone number, but user_infos.phone_no was
// NOT NULL, so every sign-up through the form failed the insert after the user row was created.
// The only test that registered passed because it sent a phone number the form does not
// collect. A download funnel that ends in a 500 at "create free account" is not a funnel; the
// column becomes nullable to match the form that feeds it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_infos', function (Blueprint $table) {
            $table->string('phone_no')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_infos', function (Blueprint $table) {
            $table->string('phone_no')->nullable(false)->change();
        });
    }
};
