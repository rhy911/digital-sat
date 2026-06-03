<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->enum('test_type', ['full_length', 'section_only', 'module_only', 'short_test', 'custom_test'])
                ->default('full_length')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->enum('test_type', ['full_length', 'section_only', 'module_only', 'short_test'])
                ->default('full_length')
                ->change();
        });
    }
};
