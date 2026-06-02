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
            $table->foreignId('current_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->timestamp('current_module_started_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropForeign(['current_module_id']);
            $table->dropColumn(['current_module_id', 'current_module_started_at']);
        });
    }
};
