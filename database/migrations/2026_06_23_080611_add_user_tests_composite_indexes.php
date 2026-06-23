<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->index(['user_id', 'test_id', 'assignment_id', 'status', 'updated_at'], 'idx_user_tests_composite');
        });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropIndex('idx_user_tests_composite');
        });
    }
};
