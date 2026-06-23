<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->string('score_conversion_version', 64)->nullable()->after('score_conversion_set_id');
            $table->string('score_estimate_kind', 32)->nullable()->after('score_conversion_version');
        });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropColumn(['score_conversion_version', 'score_estimate_kind']);
        });
    }
};
