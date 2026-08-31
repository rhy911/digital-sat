<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_RW = 'reading_writing';
    public const TYPE_MATH = 'math';

    protected $fillable = [
        'test_id',
        'name',
        'type',
        'order',
        'created_by',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo($query, $user)
    {
        if (!$user) {
            return $query->where('is_public', true);
        }
        if ($user->role === 'admin') {
            return $query;
        }
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhere('is_public', true)
              ->orWhereHas('test', function ($t) use ($user) {
                  $t->where('created_by', $user->id)
                    ->orWhere('is_public', true)
                    ->orWhereHas('shares', fn ($shares) => $shares->where('user_id', $user->id));
              });
        });
    }

    public function test()
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'section_modules')
            ->orderBy('module_number')
            ->orderBy('order');
    }

    /**
     * Compute current mean IRT difficulty separation for adaptive sections.
     *
     * @return array{has_data:bool,diff:?float,easy_mean:?float,hard_mean:?float,meets_target:bool}|null
     */
    public function adaptiveIrtDifference(): ?array
    {
        $test = $this->relationLoaded('test') ? $this->test : $this->test()->first();
        if ($test && $test->test_type !== Test::TYPE_ADAPTIVE_FULL) {
            return null;
        }

        $modules = $this->relationLoaded('modules') ? $this->modules : $this->modules()->get();
        $easy = $modules->first(fn ($m) => (int) $m->module_number === 2 && $m->difficulty_level === Module::DIFFICULTY_EASY);
        $hard = $modules->first(fn ($m) => (int) $m->module_number === 2 && $m->difficulty_level === Module::DIFFICULTY_HARD);

        if (! $easy || ! $hard) {
            return null;
        }

        $getScored = function (Module $module) {
            $questions = $module->relationLoaded('questions')
                ? $module->questions
                : $module->questions()->select(['questions.id', 'questions.is_pretest', 'questions.irt_b'])->get();

            return $questions->filter(fn ($q) => ! (bool) $q->is_pretest && is_numeric($q->irt_b));
        };

        $easyScored = $getScored($easy);
        $hardScored = $getScored($hard);

        $easyMean = $easyScored->isNotEmpty() ? $easyScored->avg('irt_b') : null;
        $hardMean = $hardScored->isNotEmpty() ? $hardScored->avg('irt_b') : null;

        if ($easyMean === null || $hardMean === null) {
            return [
                'has_data' => false,
                'diff' => null,
                'easy_mean' => $easyMean !== null ? round((float) $easyMean, 2) : null,
                'hard_mean' => $hardMean !== null ? round((float) $hardMean, 2) : null,
                'meets_target' => false,
            ];
        }

        $diff = round((float) $hardMean - (float) $easyMean, 2);

        return [
            'has_data' => true,
            'diff' => $diff,
            'easy_mean' => round((float) $easyMean, 2),
            'hard_mean' => round((float) $hardMean, 2),
            'meets_target' => $diff >= 0.5,
        ];
    }
}
