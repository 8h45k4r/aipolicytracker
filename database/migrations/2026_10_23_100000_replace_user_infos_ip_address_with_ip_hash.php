<?php

use App\Support\Privacy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_infos', function (Blueprint $table) {
            $table->string('ip_hash', 64)->nullable()->after('organization_email');
        });

        DB::table('user_infos')->whereNotNull('ip_address')->eachById(function ($row) {
            DB::table('user_infos')->where('id', $row->id)->update([
                'ip_hash' => Privacy::ipHash($row->ip_address),
            ]);
        });

        Schema::table('user_infos', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('user_infos', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('organization_email');
        });

        Schema::table('user_infos', function (Blueprint $table) {
            $table->dropColumn('ip_hash');
        });
    }
};
