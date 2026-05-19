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
        Schema::table('questions', function (Blueprint $table) {
            $table->decimal('irt_a', 4, 2)->default(0.90)->after('calculator_allowed')->comment('Discrimination');
            $table->decimal('irt_b', 4, 2)->default(0.00)->after('irt_a')->comment('Difficulty');
            $table->decimal('irt_c', 4, 2)->default(0.25)->after('irt_b')->comment('Guessing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['irt_a', 'irt_b', 'irt_c']);
        });
    }
};
