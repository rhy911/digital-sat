<?php

/**
 * The College Board skill taxonomy, keyed by section type then domain.
 *
 * This is the single source of truth for `questions.skill_domain` and
 * `questions.skill_subdomain`. Both columns are plain strings in the database
 * and are used directly as grouping keys by ScoreReportService and
 * PerformanceAnalytics, so an invented value does not fail loudly — it silently
 * becomes its own one-question "skill" and makes weak-area reports useless.
 * The bulk importer validates against this list for exactly that reason.
 *
 * The teacher import guide and the AI conversion prompt are generated from
 * here, so adding a skill in one place keeps the documentation in step.
 */
return [
    'reading_writing' => [
        'information_and_ideas' => [
            'central_ideas_and_details',
            'command_of_evidence',
            'inferences',
        ],
        'craft_and_structure' => [
            'words_in_context',
            'text_structure_and_purpose',
            'cross_text_connections',
        ],
        'expression_of_ideas' => [
            'rhetorical_synthesis',
            'transitions',
        ],
        'standard_english_conventions' => [
            'boundaries',
            'form_structure_and_sense',
        ],
    ],

    'math' => [
        'algebra' => [
            'linear_equations_in_one_variable',
            'linear_functions',
            'linear_equations_in_two_variables',
            'systems_of_two_linear_equations_in_two_variables',
            'linear_inequalities_in_one_or_two_variables',
        ],
        'advanced_math' => [
            'nonlinear_functions',
            'nonlinear_equations_in_one_variable',
            'systems_of_equations_in_two_variables',
            'equivalent_expressions',
        ],
        'problem_solving_data_analysis' => [
            'ratios_rates_proportional_relationships_and_units',
            'percentages',
            'one_variable_data_distributions_and_measures_of_center_and_spread',
            'two_variable_data_models_and_scatterplots',
            'probability_and_conditional_probability',
            'inference_from_sample_statistics_and_margin_of_error',
            'evaluating_statistical_claims_observational_studies_and_experiments',
        ],
        'geometry_trigonometry' => [
            'area_and_volume',
            'lines_angles_and_triangles',
            'right_triangles_and_trigonometry',
            'circles',
        ],
    ],
];
