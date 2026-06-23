<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_conversion_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->enum('status', ['draft', 'approved', 'retired'])->default('draft');
            $table->string('source_name');
            $table->string('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->char('form_checksum', 64)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['test_id', 'version']);
        });

        Schema::table('score_conversions', function (Blueprint $table) {
            $table->foreignId('score_conversion_set_id')
                ->nullable()
                ->after('id')
                ->constrained('score_conversion_sets')
                ->cascadeOnDelete();
            $table->unique(
                ['score_conversion_set_id', 'section_type', 'm2_difficulty', 'raw_score'],
                'uq_score_conversion_version'
            );
        });

        Schema::table('user_tests', function (Blueprint $table) {
            $table->foreignId('score_conversion_set_id')
                ->nullable()
                ->after('total_score')
                ->constrained('score_conversion_sets')
                ->nullOnDelete();
            $table->decimal('rw_theta_se', 5, 3)->nullable()->after('rw_theta');
            $table->decimal('math_theta_se', 5, 3)->nullable()->after('math_theta');
            $table->string('scoring_method', 32)->nullable()->after('math_theta_se');
        });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('score_conversion_set_id');
            $table->dropColumn(['rw_theta_se', 'math_theta_se', 'scoring_method']);
        });

        Schema::table('score_conversions', function (Blueprint $table) {
            $table->dropUnique('uq_score_conversion_version');
            $table->dropConstrainedForeignId('score_conversion_set_id');
        });

        Schema::dropIfExists('score_conversion_sets');
    }
};
