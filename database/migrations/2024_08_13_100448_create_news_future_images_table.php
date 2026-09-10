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
        Schema::create('news_future_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('news_id');
            $table->string('type');
            $table->string('name');
            $table->string('path');

            $table->softDeletes();

            $table->foreign('news_id')->references('id')->on('news')->onDelete('cascade');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_future_images');
    }
};
