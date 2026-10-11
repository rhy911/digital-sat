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
        Schema::table('classroom_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('classroom_documents', 'unit_name')) {
                $table->string('unit_name')->nullable()->after('description');
                $table->index('unit_name');
            }
            if (!Schema::hasColumn('classroom_documents', 'order')) {
                $table->unsignedInteger('order')->default(0)->after('unit_name');
            }
        });

        if (!Schema::hasTable('classroom_tests')) {
            Schema::create('classroom_tests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
                $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
                $table->string('unit_name')->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->timestamps();

                $table->unique(['classroom_id', 'test_id']);
                $table->index(['classroom_id', 'unit_name']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classroom_tests');

        Schema::table('classroom_documents', function (Blueprint $table) {
            if (Schema::hasColumn('classroom_documents', 'order')) {
                $table->dropColumn('order');
            }
            if (Schema::hasColumn('classroom_documents', 'unit_name')) {
                $table->dropIndex(['unit_name']);
                $table->dropColumn('unit_name');
            }
        });
    }
};
