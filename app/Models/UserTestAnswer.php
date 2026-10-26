<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property mixed $question_snapshot
 * @property ?string $skill_domain
 * @property int $total_answers
 * @property int $correct_answers
 * @property-read ?Question $question
 * @property-read ?Module $module
 * @property-read ?UserTestAnswerReview $review
 */
class UserTestAnswer extends Model
{
    protected $fillable = [
        'user_test_id',
        'module_id',
        'question_id',
        'selected_answer',
        'is_correct',
        'time_spent',
        'question_snapshot',
    ];

    protected $casts = [
        'question_snapshot' => 'array',
        'is_correct' => 'boolean',
    ];

    /** @return BelongsTo<UserTest, $this> */
    public function userTest(): BelongsTo
    {
        return $this->belongsTo(UserTest::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class)->withTrashed();
    }

    /** @return HasOne<UserTestAnswerReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(UserTestAnswerReview::class);
    }

    public function getQuestionAttribute(): ?Question
    {
        $resolveLive = function (): ?Question {
            if (array_key_exists('question', $this->relations)) {
                return $this->relations['question'];
            }

            /** @var Question|null $live */
            $live = $this->question_id ? $this->question()->first() : null;
            $this->setRelation('question', $live);

            return $live;
        };

        $raw = $this->question_snapshot;
        $snapshot = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);
        if (! is_array($snapshot) || empty($snapshot)) {
            return $resolveLive();
        }

        $q = new Question;
        $q->forceFill([
            'id' => $this->question_id,
            'stem' => array_key_exists('stem', $snapshot) ? $snapshot['stem'] : (($live = $resolveLive()) ? $live->stem : ''),
            'question_type' => array_key_exists('question_type', $snapshot) ? $snapshot['question_type'] : (($live = $resolveLive()) ? $live->question_type : ''),
            'difficulty' => array_key_exists('difficulty', $snapshot) ? $snapshot['difficulty'] : (($live = $resolveLive()) ? $live->difficulty : ''),
            'skill_domain' => array_key_exists('skill_domain', $snapshot) ? $snapshot['skill_domain'] : (($live = $resolveLive()) ? $live->skill_domain : ''),
            'skill_subdomain' => array_key_exists('skill_subdomain', $snapshot) ? $snapshot['skill_subdomain'] : (($live = $resolveLive()) ? $live->skill_subdomain : ''),
            'expected_time' => array_key_exists('expected_time', $snapshot) ? $snapshot['expected_time'] : (($live = $resolveLive()) ? $live->expected_time : null),
            'module_position' => array_key_exists('module_position', $snapshot)
                ? $snapshot['module_position']
                : (array_key_exists('question_number', $snapshot) ? $snapshot['question_number'] : (($live = $resolveLive()) ? $live->module_position : null)),
            'is_pretest' => array_key_exists('is_pretest', $snapshot) ? (bool) $snapshot['is_pretest'] : (bool) (($live = $resolveLive()) ? $live->is_pretest : false),
            'calculator_allowed' => array_key_exists('calculator_allowed', $snapshot) ? (bool) $snapshot['calculator_allowed'] : (bool) (($live = $resolveLive()) ? $live->calculator_allowed : false),
            'irt_a' => array_key_exists('irt_a', $snapshot) ? $snapshot['irt_a'] : (($live = $resolveLive()) ? $live->irt_a : null),
            'irt_b' => array_key_exists('irt_b', $snapshot) ? $snapshot['irt_b'] : (($live = $resolveLive()) ? $live->irt_b : null),
            'irt_c' => array_key_exists('irt_c', $snapshot) ? $snapshot['irt_c'] : (($live = $resolveLive()) ? $live->irt_c : null),
            'irt_calibration_status' => array_key_exists('irt_calibration_status', $snapshot) ? $snapshot['irt_calibration_status'] : (($live = $resolveLive()) ? $live->irt_calibration_status : 'provisional'),
            'irt_calibration_version' => array_key_exists('irt_calibration_version', $snapshot) ? $snapshot['irt_calibration_version'] : (($live = $resolveLive()) ? $live->irt_calibration_version : null),
            'section_type' => array_key_exists('section_type', $snapshot) ? $snapshot['section_type'] : (($live = $resolveLive()) ? $live->section_type : null),
        ]);
        $q->exists = true;

        if (! empty($snapshot['passage']) && is_array($snapshot['passage'])) {
            $passage = new Passage;
            $passage->forceFill([
                'content' => $snapshot['passage']['content'] ?? '',
            ]);
            $passage->exists = true;
            $q->setRelation('passage', $passage);
        } elseif (array_key_exists('passage', $snapshot)) {
            $q->setRelation('passage', null);
        } elseif ($liveInstance = $resolveLive()) {
            $q->setRelation('passage', $liveInstance->passage);
        }

        if (! empty($snapshot['choices']) && is_array($snapshot['choices'])) {
            /** @var array<int, array<string, mixed>> $choicesData */
            $choicesData = $snapshot['choices'];
            $choices = collect($choicesData)->map(function ($choice) {
                $c = new AnswerChoice;
                $c->forceFill([
                    'label' => $choice['label'] ?? '',
                    'content' => $choice['content'] ?? '',
                    'is_correct' => $choice['is_correct'] ?? false,
                    'order' => $choice['order'] ?? 0,
                ]);
                $c->exists = true;

                return $c;
            });
            $q->setRelation('answerChoices', $choices);
        } elseif (array_key_exists('choices', $snapshot)) {
            $q->setRelation('answerChoices', new \Illuminate\Database\Eloquent\Collection());
        } elseif (($liveInstance = $resolveLive()) && $liveInstance->relationLoaded('answerChoices')) {
            $q->setRelation('answerChoices', $liveInstance->answerChoices);
        }

        if (! empty($snapshot['spr_answers']) && is_array($snapshot['spr_answers'])) {
            /** @var array<int, array<string, mixed>> $sprData */
            $sprData = $snapshot['spr_answers'];
            $spr = collect($sprData)->map(function ($ans) {
                $s = new SprCorrectAnswer;
                $s->forceFill([
                    'answer' => $ans['answer'] ?? '',
                    'answer_type' => $ans['answer_type'] ?? 'exact',
                    'tolerance' => $ans['tolerance'] ?? null,
                ]);
                $s->exists = true;

                return $s;
            });
            $q->setRelation('sprCorrectAnswers', $spr);
        } elseif (array_key_exists('spr_answers', $snapshot)) {
            $q->setRelation('sprCorrectAnswers', new \Illuminate\Database\Eloquent\Collection());
        } elseif (($liveInstance = $resolveLive()) && $liveInstance->relationLoaded('sprCorrectAnswers')) {
            $q->setRelation('sprCorrectAnswers', $liveInstance->sprCorrectAnswers);
        }

        if (! empty($snapshot['explanation'])) {
            $expl = new QuestionExplanation;
            $expl->forceFill([
                'explanation' => is_array($snapshot['explanation'])
                    ? ($snapshot['explanation']['explanation'] ?? '')
                    : (string) $snapshot['explanation'],
                'rationale_a' => is_array($snapshot['explanation']) ? ($snapshot['explanation']['rationale_a'] ?? null) : null,
                'rationale_b' => is_array($snapshot['explanation']) ? ($snapshot['explanation']['rationale_b'] ?? null) : null,
                'rationale_c' => is_array($snapshot['explanation']) ? ($snapshot['explanation']['rationale_c'] ?? null) : null,
                'rationale_d' => is_array($snapshot['explanation']) ? ($snapshot['explanation']['rationale_d'] ?? null) : null,
            ]);
            $expl->exists = true;
            $q->setRelation('explanation', $expl);
        } elseif (array_key_exists('explanation', $snapshot)) {
            $q->setRelation('explanation', null);
        } elseif (($liveInstance = $resolveLive()) && $liveInstance->relationLoaded('explanation')) {
            $q->setRelation('explanation', $liveInstance->explanation);
        }

        return $q;
    }
}
