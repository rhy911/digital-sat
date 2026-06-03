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
            $table->unsignedInteger('current_module_elapsed_seconds')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropColumn('current_module_elapsed_seconds');
        });
    }
};
