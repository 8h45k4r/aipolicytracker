<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: when the provider refuses to open a checkout session the customer only
// sees a generic message, and the operator had to read server logs to learn
// the reason. Keeping the provider's response on the checkout attempt makes
// the failure visible in Admin -> Billing and auditable next to the attempt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_checkouts', function (Blueprint $table) {
            $table->text('error')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('billing_checkouts', function (Blueprint $table) {
            $table->dropColumn('error');
        });
    }
};
