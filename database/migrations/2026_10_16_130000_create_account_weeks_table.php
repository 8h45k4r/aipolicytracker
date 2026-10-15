<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reliance, measured the only way that matters: did an account act on an alert this
// week? One row per account per week that it opened a record from an alert. No IP
// address, no page path, no third party. It feeds the "relied on every week" figure.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedInteger('alert_clicks')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_weeks');
    }
};
