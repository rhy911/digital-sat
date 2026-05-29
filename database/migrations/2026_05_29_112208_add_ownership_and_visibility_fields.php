<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add fields to tests
        Schema::table('tests', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_public')
                ->default(false)
                ->after('created_by');
        });

        // 2. Add fields to sections
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('order')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_public')
                ->default(false)
                ->after('created_by');
        });

        // 3. Add fields to modules
        Schema::table('modules', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('order')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_public')
                ->default(false)
                ->after('created_by');
        });

        // 4. Add fields to questions
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('is_complete')
                ->constrained('users')
                ->nullOnDelete();
        });

        // 5. Update existing records to be public
        DB::table('tests')->update(['is_public' => true]);
        DB::table('sections')->update(['is_public' => true]);
        DB::table('modules')->update(['is_public' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop from questions
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });

        // 2. Drop from modules
        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'is_public']);
        });

        // 3. Drop from sections
        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'is_public']);
        });

        // 4. Drop from tests
        Schema::table('tests', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'is_public']);
        });
    }
};
