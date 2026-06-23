<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Delete legacy answers where module_id is null
        DB::table('user_test_answers')->whereNull('module_id')->delete();

        // 2. Drop the existing foreign key constraint
        Schema::table('user_test_answers', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
        });

        // 3. Alter column to be NOT NULL and add CASCADE onDelete constraint
        Schema::table('user_test_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable(false)->change();
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('user_test_answers', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
        });

        Schema::table('user_test_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable(true)->change();
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('set null');
        });
    }
};
