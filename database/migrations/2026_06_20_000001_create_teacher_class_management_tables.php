<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('teacher_approval_status', 20)->nullable()->after('role')->index();
            $table->foreignId('teacher_reviewed_by')->nullable()->after('teacher_approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('teacher_reviewed_at')->nullable()->after('teacher_reviewed_by');
            $table->text('teacher_rejection_reason')->nullable()->after('teacher_reviewed_at');
        });

        DB::table('users')->where('role', 'teacher')->update(['teacher_approval_status' => 'approved']);

        Schema::table('tests', function (Blueprint $table) {
            $table->timestamp('content_locked_at')->nullable()->after('is_public')->index();
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('join_code', 8)->unique();
            $table->timestamp('join_code_rotated_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('classroom_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['classroom_id', 'student_id']);
            $table->index(['classroom_id', 'status']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('test_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('instructions')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->unsignedTinyInteger('attempt_limit')->default(1);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['classroom_id', 'status']);
        });

        Schema::create('assignment_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::table('user_tests', function (Blueprint $table) {
            $table->foreignId('assignment_id')->nullable()->after('test_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_number')->nullable()->after('assignment_id');
            $table->unique(['assignment_id', 'user_id', 'attempt_number'], 'assignment_student_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropUnique('assignment_student_attempt_unique');
            $table->dropConstrainedForeignId('assignment_id');
            $table->dropColumn('attempt_number');
        });
        Schema::dropIfExists('assignment_recipients');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('classroom_memberships');
        Schema::dropIfExists('classrooms');
        Schema::table('tests', fn (Blueprint $table) => $table->dropColumn('content_locked_at'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_reviewed_by');
            $table->dropColumn(['teacher_approval_status', 'teacher_reviewed_at', 'teacher_rejection_reason']);
        });
    }
};
