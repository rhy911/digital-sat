{{-- ══════════════════════════════════════════════
     QUESTION REVIEW ACCESSIBLE DIALOG
     Rendered at body root as sibling of .app-shell for true inert containment
══════════════════════════════════════════════ --}}
<div id="reviewModal" class="sd-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="modalQuestionTitle" data-is-teacher="{{ !empty($isTeacherView) ? 'true' : 'false' }}">
    <div class="sd-modal" tabindex="-1">
        <div class="sd-modal-header">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <h3 class="sd-modal-title" id="modalQuestionTitle">Question Review</h3>
                <span id="modalQuestionSectionBadge" class="sd-section-pill rw" style="display: none;"></span>
                <span id="modalUnscoredBadge" class="sd-unscored-pill" style="display: none;" title="Unscored practice question">Unscored</span>
            </div>
            <button class="sd-modal-close" id="reviewModalCloseBtn" type="button" aria-label="Close review dialog" data-dialog-initial-focus>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>

        <div class="sd-modal-body">
            {{-- Reading Passage Box (if present) --}}
            <div id="modalPassageContainer" class="sd-modal-passage-box" style="display: none;">
                <div class="sd-modal-section-label">Reading Passage</div>
                <div class="sd-passage-content" id="modalPassageContent"></div>
            </div>

            {{-- Question Stem --}}
            <div class="sd-modal-section-label">Question Stem</div>
            <div class="sd-modal-question-box" id="modalQuestionStem"></div>

            {{-- Multiple Choice Options (if MCQ) --}}
            <div class="sd-modal-section-label js-mc-label" style="display:none;margin-top:1.25rem;">Answer Choices</div>
            <div class="sd-modal-choices-list js-mc-list" id="modalChoicesList" style="display:none;margin-bottom:1.25rem;"></div>

            {{-- Answer Comparison Row --}}
            <div class="sd-modal-answer-row">
                <div class="sd-modal-answer-box your-answer" id="modalYourAnswerBox">
                    <div class="sd-modal-answer-label">Your Answer</div>
                    <div class="sd-modal-answer-val" id="modalYourAnswer"></div>
                </div>
                <div class="sd-modal-answer-box correct-answer">
                    <div class="sd-modal-answer-label">Correct Answer</div>
                    <div class="sd-modal-answer-val" id="modalCorrectAnswer"></div>
                </div>
            </div>

            {{-- Pacing interpretation note --}}
            <div id="modalPacingInfo" style="margin-top: 1rem; padding: 0.6rem 0.85rem; background: var(--ds-surface-soft, #f8fafc); border: 1px solid var(--ds-border-default, #e2e8f0); border-radius: 8px; font-size: 0.8rem; color: var(--ds-text-secondary, #475569);">
                <span id="modalPacingText"></span>
            </div>

            {{-- Explanation Box --}}
            <div class="sd-modal-section-label" style="margin-top: 1.25rem;">Explanation</div>
            <div class="sd-modal-expl-box" id="modalExplanation"></div>

            {{-- Error Reflection / Mistake Classification --}}
            <div id="modalErrorReviewPanel" hidden style="margin-top:1.25rem; background: var(--ds-surface-soft, #f8fafc); border: 1px solid var(--ds-border-default, #e2e8f0); border-radius: 8px; padding: 1rem;">
                <label for="modalErrorType" class="sd-modal-section-label" style="display:block; margin-bottom: 0.25rem;">
                    Mistake Reflection
                </label>
                <p style="font-size:.8rem;color:var(--ds-text-secondary, #475569);margin: 0 0 .55rem;">
                    Why was this missed? Pacing labels are heuristics, while mistake causes are self-reported.
                </p>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <select id="modalErrorType" style="flex: 1; min-width: 200px; border:1px solid var(--ds-border-strong, #cbd5e1);border-radius:8px;padding:.55rem .7rem;color:var(--ds-text-primary, #0f172a);background:var(--ds-surface, #ffffff); font-size: 0.85rem;">
                        <option value="">Not classified</option>
                        <option value="conceptual_gap">Conceptual / knowledge gap</option>
                        <option value="misread_question">Misread question</option>
                        <option value="misread_text_or_data">Misread passage, text, or data</option>
                        <option value="wrong_strategy">Wrong strategy</option>
                        <option value="calculation">Calculation error</option>
                        <option value="grammar_rule">Grammar rule error</option>
                        <option value="elimination">Elimination error</option>
                        <option value="careless">Careless error</option>
                        <option value="time_pressure">Time pressure</option>
                        <option value="guess">Guess</option>
                        <option value="omitted">Omitted</option>
                    </select>
                    <button type="button" id="modalRetrySaveBtn" style="display: none; padding: 0.55rem 0.9rem; font-size: 0.8rem; font-weight: 700; border-radius: 8px; background: var(--ds-danger, #dc2626); color: #fff; border: none; cursor: pointer;">
                        Retry Save
                    </button>
                </div>
                <div id="modalTeacherOverride" hidden style="margin-top: 0.5rem; padding: 0.4rem 0.65rem; border-radius: 6px; background: var(--ds-surface-muted, #f1f5f9); border: 1px solid var(--ds-border-default, #e2e8f0); font-size: 0.775rem; color: var(--ds-text-secondary, #475569);">
                    <span id="modalTeacherOverrideLabel" style="font-weight: 700; color: var(--ds-text-primary, #0f172a);">Teacher override:</span> <span id="modalTeacherOverrideVal"></span>
                </div>
                <p id="modalErrorReviewStatus" style="font-size:.775rem;color:var(--ds-text-secondary, #475569);margin:.45rem 0 0;" aria-live="polite"></p>
            </div>
        </div>

        <div class="sd-modal-footer" style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="sd-page-btn" id="modalPrevBtn" aria-label="Previous question in filtered set">
                    &larr; Previous
                </button>
                <span id="modalTraversalCounter" style="font-size: 0.775rem; color: var(--ds-text-secondary, #475569); font-weight: 600;"></span>
                <button type="button" class="sd-page-btn" id="modalNextBtn" aria-label="Next question in filtered set">
                    Next &rarr;
                </button>
            </div>
            <button class="sd-modal-btn-close" id="reviewModalCloseBtn2" type="button">Close</button>
        </div>
    </div>
</div>
