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
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('assign_type', 20)->default('full')->after('test_id');
            $table->string('section_type', 50)->nullable()->after('assign_type');
        });

        Schema::table('user_tests', function (Blueprint $table) {
            $table->string('attempt_type', 20)->default('full')->after('assignment_id');
            $table->string('section_type', 50)->nullable()->after('attempt_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['assign_type', 'section_type']);
        });

        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropColumn(['attempt_type', 'section_type']);
        });
    }
};
