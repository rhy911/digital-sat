<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add key column to modules table and make section_id nullable
        Schema::table('modules', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
            $table->foreignId('section_id')->nullable()->change();
        });

        // 2. Create the section_modules pivot table
        Schema::create('section_modules', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('section_id')
                ->constrained()
                ->cascadeOnDelete();
                
            $table->foreignId('module_id')
                ->constrained()
                ->cascadeOnDelete();
                
            $table->timestamps();
            
            // Add unique index to prevent duplicate section-module links
            $table->unique(['section_id', 'module_id']);
        });

        // 3. Migrate existing relationships and assign default unique keys
        $existingModules = DB::table('modules')->get();
        
        foreach ($existingModules as $mod) {
            // Generate a readable, unique key
            $sect = DB::table('sections')->find($mod->section_id);
            $test = $sect ? DB::table('tests')->find($sect->test_id) : null;
            
            $testPrefix = $test ? Str::slug($test->title, '_') : 'reusable';
            $sectPrefix = $sect ? ($sect->type === 'reading_writing' ? 'rw' : 'math') : 'any';
            $diffPrefix = strtoupper($mod->difficulty_level);
            
            $baseKey = strtoupper("{$testPrefix}_{$sectPrefix}_M{$mod->module_number}_{$diffPrefix}");
            $key = $baseKey;
            
            // Handle collision (just in case)
            $count = 1;
            while (DB::table('modules')->where('key', $key)->exists()) {
                $key = "{$baseKey}_{$count}";
                $count++;
            }

            // Update key on module
            DB::table('modules')->where('id', $mod->id)->update([
                'key' => $key
            ]);

            // Link existing module to its section in the new pivot table
            if ($mod->section_id) {
                DB::table('section_modules')->insert([
                    'section_id' => $mod->section_id,
                    'module_id' => $mod->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_modules');
        
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('key');
            $table->foreignId('section_id')->nullable(false)->change();
        });
    }
};
