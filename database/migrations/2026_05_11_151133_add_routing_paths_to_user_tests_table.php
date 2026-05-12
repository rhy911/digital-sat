<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->enum('rw_m2_path', ['easy', 'hard'])->nullable()->after('score_reading_writing');
            $table->enum('math_m2_path', ['easy', 'hard'])->nullable()->after('score_math');
            $table->decimal('rw_theta', 5, 3)->nullable()->after('rw_m2_path');
            $table->decimal('math_theta', 5, 3)->nullable()->after('math_m2_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropColumn(['rw_m2_path', 'math_m2_path', 'rw_theta', 'math_theta']);
        });
    }
};
