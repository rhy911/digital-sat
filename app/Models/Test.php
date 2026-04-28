<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'test_type',
        'total_duration_minutes',
        'break_duration_minutes',
        'status',
    ];

    public function sections()
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    /**
     * Recalculate and save the total duration based on modules' durations.
     */
    public function refreshTotalDuration()
    {
        $total = 0;

        // Load sections and their modules if not already loaded
        $this->loadMissing('sections.modules');

        foreach ($this->sections as $section) {
            // Sum duration of unique module numbers in this section
            // In Digital SAT, Section 1 has Mod 1 (32m) and Mod 2 (32m)
            // Even if there are multiple versions of Mod 2 (Easy/Hard), they share the same duration.
            $sectionDuration = $section->modules
                ->unique('module_number')
                ->sum('duration_minutes');

            $total += $sectionDuration;
        }

        // Update the stored value
        $this->total_duration_minutes = $total;
        $this->save();

        return $total;
    }

    public function scoreConversions()
    {
        return $this->hasMany(ScoreConversion::class);
    }
}
