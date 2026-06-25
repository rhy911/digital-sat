<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_FULL = 'full_length';

    public const TYPE_ADAPTIVE_FULL = 'adaptive_full_length';

    protected $fillable = [
        'title',
        'description',
        'test_type',
        'total_duration_minutes',
        'break_duration_minutes',
        'status',
        'created_by',
        'is_public',
        'content_locked_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'content_locked_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($test) {
            if (empty($test->ulid)) {
                $test->ulid = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo($query, $user)
    {
        if (! $user) {
            return $query->where('is_public', true);
        }
        if ($user->role === 'admin') {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('is_public', true)
                ->orWhereHas('shares', fn ($shares) => $shares->where('user_id', $user->id));
        });
    }

    public function scopeAssignableTo($query, User $user)
    {
        return $query->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('shares', fn ($shares) => $shares->where('user_id', $user->id));
            });
    }

    public function sections()
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    public function userTests()
    {
        return $this->hasMany(UserTest::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function shares()
    {
        return $this->hasMany(TestShare::class);
    }

    public function sharedTeachers()
    {
        return $this->belongsToMany(User::class, 'test_shares')
            ->withPivot('shared_by')
            ->withTimestamps();
    }

    public function isContentLocked(): bool
    {
        return $this->assignments()->where('status', 'published')->exists();
    }

    public function isStructurallyComplete(): bool
    {
        $this->loadMissing('sections.modules.questions');

        return $this->sections->isNotEmpty()
            && $this->sections->every(fn ($section) => $section->modules->isNotEmpty()
                && $section->modules->every(fn ($module) => $module->questions->isNotEmpty()));
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

    public function scoreConversionSets()
    {
        return $this->hasMany(ScoreConversionSet::class);
    }

    public function approvedScoreConversionSet()
    {
        return $this->hasOne(ScoreConversionSet::class)
            ->where('status', ScoreConversionSet::STATUS_APPROVED)
            ->latestOfMany('version');
    }
}
