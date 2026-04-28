/**
 * Centralized State for Test Module
 */
export const state = {
  // Navigation elements
  backButton: null,
  nextButton: null,

  // Question and passage elements
  questionElements: [],
  passageElements: [],
  questionNumberSpan: null,
  totalQuestionsSpan: null,

  // Logic variables
  currentQuestionIndex: 0,
  totalQuestions: 0,
  highlightMode: false,
  persistentPopover: null,
  panelStates: [] // Track panel split ratio for each question
};
