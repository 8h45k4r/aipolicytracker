<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funders listed on /funding. They lived in config/funding.php, so adding one needed a
 * deploy. The owner now keeps them in the admin. Only published rows reach the public
 * page; while the table is empty the page still reads the config list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('kind', 16);
            // Free text as agreed ("€30,000", "$2,500 a year"), never computed.
            $table->string('amount_display', 64);
            $table->string('period', 64)->nullable();
            $table->text('purpose');
            $table->string('url', 512)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['published', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funders');
    }
};
