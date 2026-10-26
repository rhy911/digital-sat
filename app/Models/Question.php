<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $question_type
 * @property ?int $module_position
 * @property ?string $section_type
 * @property-read object{position?: int}|null $pivot
 * @property-read ?Passage $passage
 * @property-read ?QuestionExplanation $explanation
 */
class Question extends Model
{
    use SoftDeletes;

    public const TYPE_MCQ = 'multiple_choice';

    public const TYPE_SPR = 'student_produced_response';

    protected static function booted()
    {
        static::creating(function ($question) {
            // Provisional priors for new items; explicit calibration values always win.
            if ($question->irt_a === null) {
                $question->irt_a = $question->question_type === self::TYPE_SPR ? 1.3 : 0.9;
            }
            if ($question->irt_c === null) {
                $question->irt_c = $question->question_type === self::TYPE_SPR ? 0.0 : 0.25;
            }
            if ($question->irt_b === null) {
                $question->irt_b = match ($question->difficulty) {
                    'easy' => -1.2,
                    'hard' => 1.4,
                    default => 0.0,
                };
            }
        });
    }

    protected $fillable = [
        'passage_id',
        'paired_passage_id',
        'stem',
        'question_type',
        'difficulty',
        'is_pretest',
        'is_complete',
        'section_type',
        'skill_domain',
        'skill_subdomain',
        'expected_time',
        'spr_hint',
        'calculator_allowed',
        'irt_a',
        'irt_b',
        'irt_c',
        'irt_calibration_status',
        'irt_calibration_version',
        'external_id',
        'created_by',
    ];

    protected $casts = [
        'calculator_allowed' => 'boolean',
        'is_pretest' => 'boolean',
        'is_complete' => 'boolean',
        'expected_time' => 'integer',
        'irt_a' => 'float',
        'irt_b' => 'float',
        'irt_c' => 'float',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param Builder<Question> $query
     * @param ?User $user
     * @return Builder<Question>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereHas('modules', function ($m) {
                $m->where('is_public', true);
            });
        }
        if ($user->role === 'admin') {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('modules', function (Builder $m) use ($user) {
                    /** @var Builder<Module> $m */
                    $m->visibleTo($user);
                });
        });
    }

    /** @return BelongsTo<Passage, $this> */
    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    /** @return HasMany<AnswerChoice, $this> */
    public function answerChoices(): HasMany
    {
        return $this->hasMany(AnswerChoice::class);
    }

    /** @return HasOne<QuestionExplanation, $this> */
    public function explanation(): HasOne
    {
        return $this->hasOne(QuestionExplanation::class);
    }

    /** @return HasMany<QuestionMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(QuestionMedia::class);
    }

    /** @return HasMany<SprCorrectAnswer, $this> */
    public function sprCorrectAnswers(): HasMany
    {
        return $this->hasMany(SprCorrectAnswer::class);
    }

    /** @return BelongsToMany<Module, $this> */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_questions')
            ->withPivot('position');
    }
}
