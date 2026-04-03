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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 50)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->boolean('used')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('total_duration_minutes')->default(134);
            $table->enum('status', ['active','inactive','archived'])->default('active');
            $table->timestamps();
            $table->index('status', 'idx_tests_status');
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('section_number');
            $table->string('name');
            $table->enum('type', ['reading_writing','math']);
            $table->integer('duration_minutes')->default(32);
            $table->integer('total_questions')->default(27);
            $table->integer('order')->default(1);
            $table->timestamps();
            $table->index('test_id', 'idx_sections_test');
            $table->index('type', 'idx_sections_type');
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('question_number');
            $table->text('passage')->nullable();
            $table->text('question_text');
            $table->enum('question_type', ['multiple-choice','input'])->default('multiple-choice');
            $table->enum('difficulty', ['easy','medium','hard'])->default('medium');
            $table->enum('skill_category', ['information_and_ideas','craft_and_structure','expression_of_ideas','standard_english_conventions','algebra','geometry','trigonometry','arithmetic','data_analysis'])->nullable();
            $table->enum('answer_type', ['multiple-choice','input'])->default('multiple-choice');
            $table->string('input_hint')->nullable();
            $table->timestamps();
            $table->index('section_id', 'idx_questions_section');
            $table->index('difficulty', 'idx_questions_difficulty');
            $table->index('skill_category', 'idx_questions_skill');
        });

        Schema::create('answer_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('option', 10);
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
            $table->index('question_id', 'idx_answer_question');
        });

        Schema::create('test_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('status', ['in_progress','completed','paused'])->default('in_progress');
            $table->foreignId('current_section_id')->nullable()->constrained('sections')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('total_score', 8, 2)->nullable();
            $table->timestamps();
            $table->index('user_id', 'idx_sessions_user');
            $table->index('test_id', 'idx_sessions_test');
            $table->index('status', 'idx_sessions_status');
        });

        Schema::create('user_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('selected_answer', 10);
            $table->boolean('is_correct')->default(false);
            $table->integer('time_spent_seconds')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'question_id'], 'uq_response_user_question');
            $table->index('user_id', 'idx_responses_user');
            $table->index('section_id', 'idx_responses_section');
            $table->index('question_id', 'idx_responses_question');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_responses');
        Schema::dropIfExists('test_sessions');
        Schema::dropIfExists('answer_choices');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('tests');
        Schema::dropIfExists('tickets');
    }
};
