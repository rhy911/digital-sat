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
  panelStates: [], // Track panel split ratio for each question
  userTestId: null,

  // Timer state
  timeLeft: 0, // in seconds
  timerInterval: null,
  timerStartedAtMs: null,
  timerInitialSeconds: 0,
  timerPausedMs: 0,
  timerPausedStartedAtMs: null,
  timerWakeHandlersInitialized: false,
  isUntimed: false,
  isPaused: false,
  isSubmitting: false,

  // Submission state. isSubmitting must stay true for the WHOLE time a
  // submission is in flight server-side — releasing it while scoring is still
  // running is what let a second POST through and produced the
  // module_progression_conflict popup. autosaveIntervalId lets the periodic
  // autosave be stopped once a module has been submitted; there is nothing left
  // to save at that point. expiredSubmitTriggeredFor makes the autosave
  // module_expired handler fire at most once per module.
  autosaveIntervalId: null,
  expiredSubmitTriggeredFor: null,

  // Question timing
  questionActiveStartedAtMs: null,
  questionTimings: {}
};
