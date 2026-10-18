<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function userTest()
    {
        return $this->belongsTo(UserTest::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    public function module()
    {
        return $this->belongsTo(Module::class)->withTrashed();
    }

    public function review()
    {
        return $this->hasOne(UserTestAnswerReview::class);
    }

    public function getQuestionAttribute()
    {
        $resolveLive = function () {
            if (array_key_exists('question', $this->relations)) {
                return $this->relations['question'];
            }

            $live = $this->question_id ? $this->question()->first() : null;
            $this->setRelation('question', $live);

            return $live;
        };

        if ($this->question_snapshot) {
            $snapshot = is_array($this->question_snapshot) ? $this->question_snapshot : json_decode($this->question_snapshot, true);

            if (! is_array($snapshot)) {
                return $resolveLive();
            }

            $q = new Question;
            $q->forceFill([
                'id' => $this->question_id,
                'stem' => array_key_exists('stem', $snapshot) ? $snapshot['stem'] : ($resolveLive()?->stem ?? ''),
                'question_type' => array_key_exists('question_type', $snapshot) ? $snapshot['question_type'] : ($resolveLive()?->question_type ?? ''),
                'difficulty' => array_key_exists('difficulty', $snapshot) ? $snapshot['difficulty'] : ($resolveLive()?->difficulty ?? ''),
                'skill_domain' => array_key_exists('skill_domain', $snapshot) ? $snapshot['skill_domain'] : ($resolveLive()?->skill_domain ?? ''),
                'skill_subdomain' => array_key_exists('skill_subdomain', $snapshot) ? $snapshot['skill_subdomain'] : ($resolveLive()?->skill_subdomain ?? ''),
                'expected_time' => array_key_exists('expected_time', $snapshot) ? $snapshot['expected_time'] : $resolveLive()?->expected_time,
                'module_position' => array_key_exists('module_position', $snapshot)
                    ? $snapshot['module_position']
                    : (array_key_exists('question_number', $snapshot) ? $snapshot['question_number'] : $resolveLive()?->module_position),
                'is_pretest' => array_key_exists('is_pretest', $snapshot) ? (bool) $snapshot['is_pretest'] : ($resolveLive()?->is_pretest ?? false),
                'calculator_allowed' => array_key_exists('calculator_allowed', $snapshot) ? (bool) $snapshot['calculator_allowed'] : ($resolveLive()?->calculator_allowed ?? false),
                'irt_a' => array_key_exists('irt_a', $snapshot) ? $snapshot['irt_a'] : $resolveLive()?->irt_a,
                'irt_b' => array_key_exists('irt_b', $snapshot) ? $snapshot['irt_b'] : $resolveLive()?->irt_b,
                'irt_c' => array_key_exists('irt_c', $snapshot) ? $snapshot['irt_c'] : $resolveLive()?->irt_c,
                'irt_calibration_status' => array_key_exists('irt_calibration_status', $snapshot) ? $snapshot['irt_calibration_status'] : ($resolveLive()?->irt_calibration_status ?? 'provisional'),
                'irt_calibration_version' => array_key_exists('irt_calibration_version', $snapshot) ? $snapshot['irt_calibration_version'] : $resolveLive()?->irt_calibration_version,
                'section_type' => array_key_exists('section_type', $snapshot) ? $snapshot['section_type'] : $resolveLive()?->section_type,
            ]);
            $q->exists = true;

            if (! empty($snapshot['passage'])) {
                $passage = new Passage;
                $passage->forceFill([
                    'content' => $snapshot['passage']['content'] ?? '',
                ]);
                $passage->exists = true;
                $q->setRelation('passage', $passage);
            } elseif (array_key_exists('passage', $snapshot)) {
                $q->setRelation('passage', null);
            } elseif (($live = $resolveLive()) && ($live->relationLoaded('passage') || $live->passage)) {
                $q->setRelation('passage', $live->passage);
            }

            if (! empty($snapshot['choices'])) {
                $choices = collect($snapshot['choices'])->map(function ($choice) {
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
                $q->setRelation('answerChoices', collect());
            } elseif (($live = $resolveLive()) && ($live->relationLoaded('answerChoices') || $live->answerChoices)) {
                $q->setRelation('answerChoices', $live->answerChoices);
            }

            if (! empty($snapshot['spr_answers'])) {
                $spr = collect($snapshot['spr_answers'])->map(function ($ans) {
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
                $q->setRelation('sprCorrectAnswers', collect());
            } elseif (($live = $resolveLive()) && ($live->relationLoaded('sprCorrectAnswers') || $live->sprCorrectAnswers)) {
                $q->setRelation('sprCorrectAnswers', $live->sprCorrectAnswers);
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
            } elseif (($live = $resolveLive()) && ($live->relationLoaded('explanation') || $live->explanation)) {
                $q->setRelation('explanation', $live->explanation);
            }

            return $q;
        }

        return $resolveLive();
    }
}
