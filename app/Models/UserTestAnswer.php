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

    public function getQuestionAttribute()
    {
        if ($this->question_snapshot) {
            $snapshot = is_array($this->question_snapshot) ? $this->question_snapshot : json_decode($this->question_snapshot, true);

            $q = new Question;
            $q->forceFill([
                'id' => $this->question_id,
                'stem' => $snapshot['stem'] ?? '',
                'question_type' => $snapshot['question_type'] ?? '',
                'difficulty' => $snapshot['difficulty'] ?? '',
                'skill_domain' => $snapshot['skill_domain'] ?? '',
                'skill_subdomain' => $snapshot['skill_subdomain'] ?? '',
                'is_pretest' => $snapshot['is_pretest'] ?? false,
                'calculator_allowed' => $snapshot['calculator_allowed'] ?? false,
                'irt_a' => $snapshot['irt_a'] ?? null,
                'irt_b' => $snapshot['irt_b'] ?? null,
                'irt_c' => $snapshot['irt_c'] ?? null,
                'irt_calibration_status' => $snapshot['irt_calibration_status'] ?? 'provisional',
                'irt_calibration_version' => $snapshot['irt_calibration_version'] ?? null,
                'section_type' => $snapshot['section_type'] ?? null,
            ]);
            $q->exists = true;

            if (! empty($snapshot['passage'])) {
                $passage = new Passage;
                $passage->forceFill([
                    'content' => $snapshot['passage']['content'] ?? '',
                ]);
                $passage->exists = true;
                $q->setRelation('passage', $passage);
            }

            $choices = collect($snapshot['choices'] ?? [])->map(function ($choice) {
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

            $spr = collect($snapshot['spr_answers'] ?? [])->map(function ($ans) {
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

            if (! empty($snapshot['explanation'])) {
                $expl = new QuestionExplanation;
                $expl->forceFill([
                    'explanation' => $snapshot['explanation']['explanation'] ?? '',
                    'rationale_a' => $snapshot['explanation']['rationale_a'] ?? null,
                    'rationale_b' => $snapshot['explanation']['rationale_b'] ?? null,
                    'rationale_c' => $snapshot['explanation']['rationale_c'] ?? null,
                    'rationale_d' => $snapshot['explanation']['rationale_d'] ?? null,
                ]);
                $expl->exists = true;
                $q->setRelation('explanation', $expl);
            }

            return $q;
        }

        return $this->relations['question'] ?? $this->question()->first();
    }
}
