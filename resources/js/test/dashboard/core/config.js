export const SKILL_DOMAINS = {
    reading_writing: [
        { value: 'craft_and_structure', label: 'Craft and Structure' },
        { value: 'information_and_ideas', label: 'Information and Ideas' },
        { value: 'standard_english_conventions', label: 'Standard English Conventions' },
        { value: 'expression_of_ideas', label: 'Expression of Ideas' }
    ],
    math: [
        { value: 'algebra', label: 'Algebra' },
        { value: 'advanced_math', label: 'Advanced Math' },
        { value: 'problem_solving_data_analysis', label: 'Problem-Solving and Data Analysis' },
        { value: 'geometry_trigonometry', label: 'Geometry and Trigonometry' }
    ]
};

export const config = window.TestDashboardConfig || {};
export const {
    SNAPSHOT_URL,
    QUESTIONS_LIST_URL,
    QUESTIONS_SEARCH_URL,
    CSV_BULK_URL,
    BULK_PREVIEW_URL,
    CSV_BULK_PREVIEW_URL,
    QUESTIONS_PER_PAGE = 30,
    BULK_STORE_URL,
    MEDIA_UPLOAD_URL,
    TESTS_STORE_URL,
    SECTIONS_STORE_URL,
    SECTIONS_LINK_MODULE_URL,
    MODULES_STORE_URL,
    QUESTIONS_ATTACH_URL,
    BASE_URL,
} = config;

export const TEST_DASHBOARD_TAB_KEY = 'testDashboardActiveTab';

// Shared state that was previously on window
if (typeof window.__tdQuestionsPage === 'undefined') {
    window.__tdQuestionsPage = 1;
}
if (typeof window.__tdQuestionsPerPage === 'undefined') {
    window.__tdQuestionsPerPage = QUESTIONS_PER_PAGE;
}
if (typeof window.__tdQuestionsQuery === 'undefined') {
    window.__tdQuestionsQuery = '';
}
if (typeof window.__tdLatestTests === 'undefined') {
    window.__tdLatestTests = window.__tdTestsData || [];
}
