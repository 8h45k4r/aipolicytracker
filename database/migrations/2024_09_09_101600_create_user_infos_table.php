<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_infos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('phone_no');
            $table->string('organization_name')->nullable()->default(null);
            $table->string('organization_email')->nullable()->default(null);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('last_activity')->index();
            $table->string('password')->nullable()->default(null);
            $table->boolean('status')->nullable()->default(true);
            $table->boolean('terms_condition')->nullable()->default(false);

            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_infos');
    }
};
