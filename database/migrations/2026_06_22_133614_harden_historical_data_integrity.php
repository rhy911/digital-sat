<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_test_answers', function (Blueprint $table) {
            $table->json('question_snapshot')->nullable()->after('is_correct');
        });

        Schema::table('tests', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('sections', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('modules', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('questions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropForeign(['test_id']);
            $table->foreign('test_id')->references('id')->on('tests')->onDelete('restrict');
        });

        Schema::table('user_test_answers', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('user_test_answers', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
            $table->dropColumn('question_snapshot');
        });

        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropForeign(['test_id']);
            $table->foreign('test_id')->references('id')->on('tests')->onDelete('cascade');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('modules', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('sections', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('tests', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
