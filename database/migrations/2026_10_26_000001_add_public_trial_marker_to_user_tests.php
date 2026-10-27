<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->boolean('is_public_trial')->default(false)->index();
        });

        $trialUlids = array_values(array_filter(config('public_trial.tests', [])));
        if ($trialUlids !== []) {
            DB::table('user_tests')
                ->whereNull('assignment_id')
                ->whereNull('exam_session_id')
                ->whereIn('test_id', DB::table('tests')->select('id')->whereIn('ulid', $trialUlids))
                ->whereIn('user_id', DB::table('users')->select('id')->where('is_guest', true))
                ->update(['is_public_trial' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropIndex(['is_public_trial']);
            $table->dropColumn('is_public_trial');
        });
    }
};
