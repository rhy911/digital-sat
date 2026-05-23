export const TestDashboardExamples = {
    RW_JSON: {
        items: [{
            stem: 'Which choice best describes the **main idea** of the text?',
            question_type: 'multiple_choice',
            difficulty: 'medium',
            skill_domain: 'information_and_ideas',
            passage: {
                content: 'The researcher noted that early observations were incomplete, yet they shaped every later hypothesis.',
                source_title: 'Field notes (fictional sample)'
            },
            choices: [{
                    label: 'A',
                    content: 'Early observations were useless.',
                    is_correct: false
                },
                {
                    label: 'B',
                    content: 'Initial incomplete work influenced later science.',
                    is_correct: true
                },
                {
                    label: 'C',
                    content: 'Later teams refused to use older data.',
                    is_correct: false
                },
                {
                    label: 'D',
                    content: 'Hypotheses are never revised.',
                    is_correct: false
                }
            ],
            explanation: 'The passage stresses that early incomplete observations still shaped later hypotheses.'
        }]
    },
    MATH_JSON: {
        items: [{
                stem: 'What is **2 + 2**?',
                question_type: 'multiple_choice',
                difficulty: 'easy',
                skill_domain: 'algebra',
                choices: [{
                        label: 'A',
                        content: '3',
                        is_correct: false
                    },
                    {
                        label: 'B',
                        content: '4',
                        is_correct: true
                    },
                    {
                        label: 'C',
                        content: '5',
                        is_correct: false
                    },
                    {
                        label: 'D',
                        content: '6',
                        is_correct: false
                    }
                ],
                explanation: 'The sum of 2 and 2 is 4.'
            },
            {
                stem: 'If $$x^2 = 9$$, what is the **positive** value of $$x$$?',
                question_type: 'student_produced_response',
                difficulty: 'medium',
                skill_domain: 'advanced_math',
                spr_correct_answers: ['3'],
                spr_hint: 'Enter a positive number only.',
                explanation: 'The positive square root of 9 is 3.'
            }
        ]
    }
};

if (typeof window !== 'undefined') {
    window.TestDashboardExamples = TestDashboardExamples;
}
