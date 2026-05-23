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
        Schema::table('tests', function (Blueprint $blueprint) {
            $blueprint->enum('test_type', ['full_length', 'section_only', 'module_only', 'short_test'])
                ->default('full_length')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tests', function (Blueprint $blueprint) {
            $blueprint->enum('test_type', ['full_length', 'section_only', 'mini_quiz'])
                ->default('full_length')
                ->change();
        });
    }
};
