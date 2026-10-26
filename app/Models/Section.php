<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $type
 * @property-read string $section_type
 * @property-read ?Test $test
 */
class Section extends Model
{
    use SoftDeletes;

    public const TYPE_RW = 'reading_writing';
    public const TYPE_MATH = 'math';

    public function getSectionTypeAttribute(): string
    {
        return (string) ($this->type ?? '');
    }

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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param Builder<Section> $query
     * @param ?User $user
     * @return Builder<Section>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
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

    /** @return BelongsTo<Test, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    /** @return BelongsToMany<Module, $this> */
    public function modules(): BelongsToMany
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

            return $questions->filter(fn ($q) => ! (bool) $q->is_pretest);
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
