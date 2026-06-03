<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });

        DB::table('user_tests')
            ->whereNull('ulid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('user_tests')
                        ->where('id', $row->id)
                        ->update(['ulid' => (string) Str::ulid()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('user_tests', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
    }
};
