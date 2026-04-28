<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng tests — đề thi tổng thể.
     * full_length = 2 section + 4 module = 134 phút + 10 phút break.
     */
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255)
                ->comment('VD: Digital SAT Practice Test 1');

            $table->text('description')
                ->nullable();

            $table->enum('test_type', ['full_length', 'section_only', 'mini_quiz'])
                ->default('full_length')
                ->comment('full_length | section_only | mini_quiz');

            $table->integer('total_duration_minutes')
                ->default(134);

            $table->integer('break_duration_minutes')
                ->default(10)
                ->comment('Break giữa Section 1 và 2');

            $table->enum('status', ['draft', 'active', 'archived'])
                ->default('active')
                ->comment('draft | active | archived');

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng tests.
     */
    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
