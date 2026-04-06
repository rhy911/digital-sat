<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng module_routing — adaptive routing.
     * Điểm M1 >= threshold → M2 hard, ngược lại → M2 easy.
     */
    public function up(): void
    {
        Schema::create('module_routing', function (Blueprint $table) {
            $table->id();

            $table->foreignId('from_module_id')
                ->constrained('modules')
                ->cascadeOnDelete()
                ->comment('Module 1 (standard)');

            $table->foreignId('to_module_id')
                ->constrained('modules')
                ->cascadeOnDelete()
                ->comment('Module 2 (easy hoặc hard)');

            $table->enum('condition', ['score_above', 'score_below_equal'])
                ->comment('score_above | score_below_equal');

            $table->integer('threshold_score')
                ->comment('Số câu đúng tối thiểu để route');

            $table->timestamps();
        });
    }

    /**
     * Rollback: xóa bảng module_routing.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_routing');
    }
};
