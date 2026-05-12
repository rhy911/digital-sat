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
        Schema::create('user_test_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('selected_answer')->nullable()->comment('Label A/B/C/D or SPR value');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['user_test_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_test_answers');
    }
};
