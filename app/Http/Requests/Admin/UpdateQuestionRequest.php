<?php

namespace App\Http\Requests\Admin;

use App\Support\SatTaxonomy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = \App\Models\Question::findOrFail($this->route('id'));
        return $this->user()?->can('update', $question) ?? false;
    }

    protected function prepareForValidation()
    {
        if ($this->has('spr_answers')) {
            $val = $this->input('spr_answers');
            if (is_array($val)) {
                $this->merge(['spr_answers' => implode(', ', array_filter($val))]);
            } elseif ($val === null) {
                $this->merge(['spr_answers' => '']);
            }
        } else {
            if ($this->input('question_type') === \App\Models\Question::TYPE_SPR) {
                $this->merge(['spr_answers' => '']);
            }
        }
    }

    public function rules(): array
    {
        return [
            'stem' => 'required|string',
            'question_type' => 'required|in:multiple_choice,student_produced_response',
            'difficulty' => 'nullable|in:easy,medium,hard',
            // Constrained to config/sat_taxonomy.php: both columns are used as
            // grouping keys by the score report and the analytics summaries, so
            // an invented value silently becomes its own one-question "skill".
            'skill_domain' => ['nullable', 'string', Rule::in(SatTaxonomy::domains())],
            'skill_subdomain' => ['nullable', 'string'],
            'spr_hint' => 'nullable|string',
            'is_pretest' => 'boolean',
            'calculator_allowed' => 'boolean',
            'passage_content' => 'nullable|string',
            'expected_time' => 'nullable|integer|min:0',
            
            // Choices & SPR & Explanation
            'correct_choice' => 'required_if:question_type,multiple_choice|string|max:1',
            'choices' => 'required_if:question_type,multiple_choice|array',
            'spr_answers' => 'nullable|string',
            'explanation' => 'nullable|string',
            'rationale_a' => 'nullable|string',
            'rationale_b' => 'nullable|string',
            'rationale_c' => 'nullable|string',
            'rationale_d' => 'nullable|string',
        ];
    }

    /**
     * Custom validation rules checking for SPR answer necessity after basic validation.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('question_type') === \App\Models\Question::TYPE_SPR && empty($this->input('spr_answers'))) {
                $validator->errors()->add('spr_answers', 'The spr answers field is required.');
            }

            $domain = (string) $this->input('skill_domain', '');
            $subdomain = trim((string) $this->input('skill_subdomain', ''));

            if ($domain === '' || $subdomain === '' || SatTaxonomy::isValidSubdomain($domain, $subdomain)) {
                return;
            }

            $belongsTo = SatTaxonomy::domainForSubdomain($subdomain);
            $validator->errors()->add('skill_subdomain', $belongsTo !== null
                ? "This skill belongs to \"{$belongsTo}\", not \"{$domain}\"."
                : 'Allowed for '.$domain.': '.implode(', ', SatTaxonomy::subdomains($domain)).'.');
        });
    }
}
