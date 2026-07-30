<?php

return [
    'routing' => [
        // Module 1 -> Module 2 routing is theta-based (>= cutoff routes to Hard),
        // NOT number-correct: number-correct is only a sufficient statistic for theta
        // under Rasch/1PL and loses accuracy under our 3PL (varying a/c). Cutoff is a
        // global per-section default (no per-form entry, no manual work per test).
        'theta_cutoff' => [
            'default' => 0.0,
            'reading_writing' => 0.0,
            'math' => 0.0,
        ],
    ],

    // Fallback per-question pacing target (seconds) used only when a question has
    // no admin-entered expected_time. Midpoint of realistic SAT per-difficulty pacing
    // bands (Reading & Writing: easy 30-45s, medium 60-75s, hard 90-120s; Math: easy
    // 30-50s, medium 60-90s, hard 120-180s). "unknown" (untagged difficulty) falls
    // back to the medium band.
    'pacing_defaults' => [
        'reading_writing' => ['easy' => 38, 'medium' => 68, 'hard' => 105, 'unknown' => 68],
        'math' => ['easy' => 40, 'medium' => 75, 'hard' => 150, 'unknown' => 75],
    ],
    'normal_conversion' => [
        'version' => 'normal_consensus_v1',
        'source_name' => 'DigiSAT reviewed calculator consensus',
        'retrieved_at' => '2026-06-22',
        'source_urls' => [
            'https://www.albert.io/blog/sat-score-calculator/',
        ],
        'notes' => 'Provisional route-neutral practice table. External research access was unavailable during implementation; 45/54 R&W plus 35/44 Math is frozen at 1270 from the user-verified Albert benchmark.',
        'tables' => [
            'reading_writing' => [
                0 => 200, 1 => 200, 2 => 200, 3 => 210, 4 => 220, 5 => 230, 6 => 240, 7 => 250, 8 => 260, 9 => 270,
                10 => 280, 11 => 290, 12 => 300, 13 => 310, 14 => 320, 15 => 330, 16 => 340, 17 => 350, 18 => 360, 19 => 370,
                20 => 380, 21 => 390, 22 => 400, 23 => 410, 24 => 420, 25 => 430, 26 => 440, 27 => 450, 28 => 460, 29 => 470,
                30 => 480, 31 => 490, 32 => 500, 33 => 510, 34 => 520, 35 => 530, 36 => 540, 37 => 550, 38 => 560, 39 => 570,
                40 => 580, 41 => 590, 42 => 600, 43 => 610, 44 => 620, 45 => 630, 46 => 650, 47 => 670, 48 => 690, 49 => 710,
                50 => 730, 51 => 750, 52 => 770, 53 => 790, 54 => 800,
            ],
            'math' => [
                0 => 200, 1 => 210, 2 => 220, 3 => 230, 4 => 240, 5 => 250, 6 => 260, 7 => 270, 8 => 280, 9 => 290,
                10 => 300, 11 => 310, 12 => 320, 13 => 330, 14 => 340, 15 => 350, 16 => 360, 17 => 370, 18 => 380, 19 => 390,
                20 => 400, 21 => 410, 22 => 420, 23 => 430, 24 => 440, 25 => 450, 26 => 460, 27 => 470, 28 => 480, 29 => 490,
                30 => 500, 31 => 530, 32 => 560, 33 => 590, 34 => 620, 35 => 640, 36 => 660, 37 => 690, 38 => 710, 39 => 730,
                40 => 750, 41 => 770, 42 => 780, 43 => 790, 44 => 800,
            ],
        ],
    ],
    'adaptive_conversion' => [
        'version' => 'irt_curve_v1',
        'minimum' => 200,
        'maximum' => 800,
        'round_to' => 10,
        // Used only to scale the theta standard error into a scaled-score band width
        // for the total-score quadrature; the point/lower/upper scores come from the
        // curves below, not from a linear points-per-theta slope.
        'points_per_theta' => 100,

        // Path-aware, section-specific theta -> scaled-score anchor curves.
        // Piecewise-linear between ascending [theta, scaled] anchors; clamped to the
        // first/last anchor outside the range. Calibrated (v1) from public Bluebook
        // behaviour research, NOT official College Board tables:
        //   - Easy Module 2 caps a section near ~670 (RW) / ~665 (Math).
        //   - Hard Module 2 opens the full range to 800 (RW 800 at theta~2.9 to allow
        //     ~2 wrong; Math kept stricter, 800 at theta~3.0).
        //   - Easy and hard agree for theta <= 1.0; easy bends toward the cap above that.
        // Tunable; superseded per question by auto-calibrated IRT params once real
        // response data exists.
        'curves' => [
            'reading_writing' => [
                'hard' => [
                    [-4.0, 200], [-3.0, 240], [-2.0, 320], [-1.0, 420], [-0.5, 460],
                    [0.0, 510], [0.5, 550], [1.0, 600], [1.5, 650], [2.0, 700],
                    [2.5, 760], [2.9, 800],
                ],
                'easy' => [
                    [-4.0, 200], [-3.0, 240], [-2.0, 320], [-1.0, 420], [-0.5, 460],
                    [0.0, 510], [0.5, 550], [1.0, 600], [1.5, 640], [2.0, 660],
                    [2.5, 670],
                ],
            ],
            'math' => [
                'hard' => [
                    [-4.0, 200], [-3.0, 240], [-2.0, 310], [-1.0, 410], [-0.5, 460],
                    [0.0, 500], [0.5, 550], [1.0, 600], [1.5, 660], [2.0, 720],
                    [2.5, 770], [3.0, 800],
                ],
                'easy' => [
                    [-4.0, 200], [-3.0, 240], [-2.0, 310], [-1.0, 410], [-0.5, 460],
                    [0.0, 500], [0.5, 550], [1.0, 600], [1.5, 630], [2.0, 650],
                    [2.5, 660],
                ],
            ],
        ],
    ],
];
