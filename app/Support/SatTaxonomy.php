<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Read access to config/sat_taxonomy.php and single source of truth
 * for official Digital SAT domains and skills display naming.
 */
class SatTaxonomy
{
    /**
     * Official Digital SAT Domain display labels.
     */
    public const DOMAIN_LABELS = [
        // Reading and Writing
        'craft_and_structure' => 'Craft and Structure',
        'information_and_ideas' => 'Information and Ideas',
        'standard_english_conventions' => 'Standard English Conventions',
        'expression_of_ideas' => 'Expression of Ideas',

        // Math
        'algebra' => 'Algebra',
        'advanced_math' => 'Advanced Math',
        'problem_solving_data_analysis' => 'Problem-Solving and Data Analysis',
        'problem_solving_and_data_analysis' => 'Problem-Solving and Data Analysis',
        'problem_solving' => 'Problem-Solving and Data Analysis',
        'geometry_trigonometry' => 'Geometry and Trigonometry',
        'geometry_and_trigonometry' => 'Geometry and Trigonometry',
        'geometry' => 'Geometry and Trigonometry',
    ];

    /**
     * Official Digital SAT Skill / Testing Point display labels matching report specification:
     *
     * Reading and Writing:
     * - Craft and Structure: Words in Context, Text Structure and Purpose, Cross-Text Connections
     * - Information and Ideas: Central Ideas and Details, Command of Evidence (Textual & Quantitative), Inferences
     * - Standard English Conventions: Boundaries, Form, Structure, and Sense
     * - Expression of Ideas: Rhetorical Synthesis, Transitions
     *
     * Math:
     * - Algebra: Linear Equations in 1 Variable, Linear Equations in 2 Variables, Linear Functions, Systems of 2 Linear Equations, Linear Inequalities
     * - Advanced Math: Equivalent Expressions, Nonlinear Equations (1 variable & Systems), Nonlinear Functions
     * - Problem-Solving and Data Analysis: Ratios, Rates, Proportional Relationships, & Units, Percentages, Data Distributions & Measures of Center/Spread, Two-Way Tables & Evaluative Statistics, Probability & Sample Evaluation
     * - Geometry and Trigonometry: Area and Volume, Lines, Angles, and Triangles, Right Triangles and Trigonometric Ratios, Circles
     */
    public const SUBDOMAIN_LABELS = [
        // -------------------------------------------------------------
        // READING & WRITING: Craft and Structure
        // -------------------------------------------------------------
        'words_in_context' => 'Words in Context',
        'text_structure_and_purpose' => 'Text Structure and Purpose',
        'cross_text_connections' => 'Cross-Text Connections',

        // -------------------------------------------------------------
        // READING & WRITING: Information and Ideas
        // -------------------------------------------------------------
        'central_ideas_and_details' => 'Central Ideas and Details',
        'command_of_evidence' => 'Command of Evidence (Textual & Quantitative)',
        'command_of_evidence_textual' => 'Command of Evidence (Textual & Quantitative)',
        'command_of_evidence_quantitative' => 'Command of Evidence (Textual & Quantitative)',
        'inferences' => 'Inferences',

        // -------------------------------------------------------------
        // READING & WRITING: Standard English Conventions
        // -------------------------------------------------------------
        'boundaries' => 'Boundaries',
        'form_structure_and_sense' => 'Form, Structure, and Sense',

        // -------------------------------------------------------------
        // READING & WRITING: Expression of Ideas
        // -------------------------------------------------------------
        'rhetorical_synthesis' => 'Rhetorical Synthesis',
        'transitions' => 'Transitions',

        // -------------------------------------------------------------
        // MATH: Algebra
        // -------------------------------------------------------------
        'linear_equations_in_one_variable' => 'Linear Equations in 1 Variable',
        'linear_equations_in_1_variable' => 'Linear Equations in 1 Variable',
        'linear_equations_with_parameters_and_infinite_solutions' => 'Linear Equations in 1 Variable',
        'linear_equations_in_two_variables' => 'Linear Equations in 2 Variables',
        'linear_equations_in_2_variables' => 'Linear Equations in 2 Variables',
        'linear_functions' => 'Linear Functions',
        'linear_functions_and_intercepts' => 'Linear Functions',
        'linear_functions_in_context' => 'Linear Functions',
        'systems_of_two_linear_equations_in_two_variables' => 'Systems of 2 Linear Equations',
        'systems_of_2_linear_equations' => 'Systems of 2 Linear Equations',
        'systems_of_linear_equations' => 'Systems of 2 Linear Equations',
        'systems_of_linear_equations_with_parameters' => 'Systems of 2 Linear Equations',
        'linear_systems_word_problems' => 'Systems of 2 Linear Equations',
        'linear_inequalities_in_one_or_two_variables' => 'Linear Inequalities',
        'linear_inequalities_in_one_variable' => 'Linear Inequalities',
        'linear_inequalities' => 'Linear Inequalities',
        'systems_of_linear_inequalities_maximums' => 'Linear Inequalities',

        // -------------------------------------------------------------
        // MATH: Advanced Math
        // -------------------------------------------------------------
        'equivalent_expressions' => 'Equivalent Expressions',
        'exponential_equivalence_and_unit_scaling' => 'Equivalent Expressions',
        'rational_exponents_and_radicals' => 'Equivalent Expressions',
        'nested_radicals_and_extraneous_solutions' => 'Equivalent Expressions',
        'polynomial_roots_and_intercepts' => 'Equivalent Expressions',
        'vietas_formulas_and_polynomial_roots' => 'Equivalent Expressions',
        'nonlinear_equations_in_one_variable' => 'Nonlinear Equations (1 variable & Systems)',
        'systems_of_equations_in_two_variables' => 'Nonlinear Equations (1 variable & Systems)',
        'nonlinear_equations_in_one_variable_and_systems_of_equations_in_two_variables' => 'Nonlinear Equations (1 variable & Systems)',
        'nonlinear_equations_(1_variable_&_systems)' => 'Nonlinear Equations (1 variable & Systems)',
        'quadratic_equations_and_vietas_formula' => 'Nonlinear Equations (1 variable & Systems)',
        'radical_equations_and_extraneous_solutions' => 'Nonlinear Equations (1 variable & Systems)',
        'rational_equations_and_extraneous_solutions' => 'Nonlinear Equations (1 variable & Systems)',
        'nonlinear_functions' => 'Nonlinear Functions',
        'exponential_functions_and_growth_rates' => 'Nonlinear Functions',
        'nonlinear_graph_transformations' => 'Nonlinear Functions',
        'vertex_form_and_parabolas' => 'Nonlinear Functions',
        'intersection_of_linear_and_quadratic_models_discriminant' => 'Nonlinear Functions',
        'intersection_of_quadratic_and_linear_models_discriminant' => 'Nonlinear Functions',

        // -------------------------------------------------------------
        // MATH: Problem-Solving and Data Analysis
        // -------------------------------------------------------------
        'ratios_rates_proportional_relationships_and_units' => 'Ratios, Rates, Proportional Relationships, & Units',
        'ratios_rates_proportional_relationships_&_units' => 'Ratios, Rates, Proportional Relationships, & Units',
        'ratios_rates_and_proportions' => 'Ratios, Rates, Proportional Relationships, & Units',
        'complex_proportions_and_mixtures' => 'Ratios, Rates, Proportional Relationships, & Units',
        'percentages' => 'Percentages',
        'percentages_and_reverse_percentages' => 'Percentages',
        'successive_percentages' => 'Percentages',
        'combinatorics_and_nested_percentages' => 'Percentages',
        'one_variable_data_distributions_and_measures_of_center_and_spread' => 'Data Distributions & Measures of Center/Spread',
        'data_distributions_&_measures_of_center/spread' => 'Data Distributions & Measures of Center/Spread',
        'data_distributions_and_central_tendency' => 'Data Distributions & Measures of Center/Spread',
        'measures_of_center_and_missing_data' => 'Data Distributions & Measures of Center/Spread',
        'median_logic_with_missing_frequencies' => 'Data Distributions & Measures of Center/Spread',
        'two_variable_data_models_and_scatterplots' => 'Two-Way Tables & Evaluative Statistics',
        'two_way_tables_&_evaluative_statistics' => 'Two-Way Tables & Evaluative Statistics',
        'scatterplots_and_linear_models' => 'Two-Way Tables & Evaluative Statistics',
        'evaluating_statistical_claims_observational_studies_and_experiments' => 'Two-Way Tables & Evaluative Statistics',
        'probability_and_conditional_probability' => 'Probability & Sample Evaluation',
        'probability_&_sample_evaluation' => 'Probability & Sample Evaluation',
        'inference_from_sample_statistics_and_margin_of_error' => 'Probability & Sample Evaluation',

        // -------------------------------------------------------------
        // MATH: Geometry and Trigonometry
        // -------------------------------------------------------------
        'area_and_volume' => 'Area and Volume',
        'area_and_perimeter_of_polygons' => 'Area and Volume',
        '3d_volume_and_basic_constraints' => 'Area and Volume',
        '3d_volume_and_structural_scaling' => 'Area and Volume',
        'lines_angles_and_triangles' => 'Lines, Angles, and Triangles',
        'parallel_lines_and_angle_relationships' => 'Lines, Angles, and Triangles',
        'parallel_and_perpendicular_lines' => 'Lines, Angles, and Triangles',
        'right_triangles_and_trigonometry' => 'Right Triangles and Trigonometric Ratios',
        'right_triangles_and_trigonometric_ratios' => 'Right Triangles and Trigonometric Ratios',
        'right_triangles_and_pythagorean_theorem' => 'Right Triangles and Trigonometric Ratios',
        'circles' => 'Circles',
        'circle_equations_and_completing_the_square' => 'Circles',
        'circle_tangents_and_perpendicular_slopes' => 'Circles',
        'arc_length_and_radians' => 'Circles',
    ];

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function all(): array
    {
        return config('sat_taxonomy', []);
    }

    /**
     * Domains allowed for a section, or every domain when the section is unknown.
     *
     * @return list<string>
     */
    public static function domains(?string $sectionType = null): array
    {
        $taxonomy = self::all();

        if ($sectionType !== null && isset($taxonomy[$sectionType])) {
            return array_keys($taxonomy[$sectionType]);
        }

        return array_values(array_unique(array_merge(...array_map(
            'array_keys',
            array_values($taxonomy)
        ))));
    }

    /**
     * @return list<string>
     */
    public static function subdomains(string $domain): array
    {
        foreach (self::all() as $domains) {
            if (isset($domains[$domain])) {
                return $domains[$domain];
            }
        }

        return [];
    }

    public static function isValidDomain(string $domain, ?string $sectionType = null): bool
    {
        return in_array($domain, self::domains($sectionType), true);
    }

    public static function isValidSubdomain(string $domain, string $subdomain): bool
    {
        return in_array($subdomain, self::subdomains($domain), true);
    }

    /**
     * The domain a subdomain belongs to, or null when it belongs to none.
     */
    public static function domainForSubdomain(string $subdomain): ?string
    {
        foreach (self::all() as $domains) {
            foreach ($domains as $domain => $subdomains) {
                if (in_array($subdomain, $subdomains, true)) {
                    return $domain;
                }
            }
        }

        return null;
    }

    /**
     * Resolve official College Board Bluebook display label for a domain.
     */
    public static function domainLabel(string $domain): string
    {
        $normalized = strtolower(trim($domain));

        return self::DOMAIN_LABELS[$normalized]
            ?? self::DOMAIN_LABELS[Str::snake($domain)]
            ?? Str::of($domain)->replace('_', ' ')->title()->toString();
    }

    /**
     * Resolve official College Board Bluebook display label for a skill/subdomain.
     */
    public static function subdomainLabel(string $subdomain): string
    {
        $trimmed = trim($subdomain);
        if ($trimmed === '' || strtolower($trimmed) === 'other' || strtolower($trimmed) === 'unknown') {
            return 'General Skills';
        }

        $normalized = strtolower($trimmed);
        if (isset(self::SUBDOMAIN_LABELS[$normalized])) {
            return self::SUBDOMAIN_LABELS[$normalized];
        }

        $snake = Str::snake($subdomain);
        if (isset(self::SUBDOMAIN_LABELS[$snake])) {
            return self::SUBDOMAIN_LABELS[$snake];
        }

        // Clean title-cased fallback with proper small word casing
        $formatted = Str::of($subdomain)->replace('_', ' ')->title()->toString();

        return str_ireplace(
            [' And ', ' In ', ' Of ', ' For ', ' To ', ' With ', ' From '],
            [' and ', ' in ', ' of ', ' for ', ' to ', ' with ', ' from '],
            $formatted
        );
    }
}
