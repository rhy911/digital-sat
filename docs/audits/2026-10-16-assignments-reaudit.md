# Assignments re-audit — student and teacher

Date: 2026-10-16. Scope: the updated Assignments workspaces, teacher Results/Analysis/Settings, question inspection, student readiness, and shared navigation. Audit only; no application fixes applied.

Baseline: [original audit](D:/Projects/php/digital-sat/docs/audits/2026-10-16-assignments.md).

## Implementation integrity verdict

**Pass, with remaining defects.** The updated implementation preserves the classroom identity and now connects its shared components to working keyboard navigation, dialog management, labeled inputs, contained tables, and consistent status values. Live checks confirm those changes. The remaining concerns are incomplete contrast corrections, eagerly loaded analysis data, and schedule-picker dismissal.

The bundled detector again returned five warnings: three conditional-class `gray-on-color` matches at teacher view lines 766, 773, and 907, the unused `.pinned-note-card` stripe at workspace CSS line 955, and the documented classroom font at line 96. The conditional warnings combine mutually exclusive classes; the stripe is outside these rendered views; the font is explicitly approved in `DESIGN.md`. None adds a verified issue. Runtime contrast failures below are independent measurements.

## Audit health

**15/20 — Good, up from 10/20.** Of the original 13 findings, **11 are resolved and 2 are partially resolved**. One additional behavior issue was identified; it was not tested in the baseline, so it is not established as a new regression.

**Current issue count: 0 P0, 1 P1, 2 P2, 0 P3.** Repeated contrast failures are grouped as one root finding.

| Dimension | Before | Now | Evidence |
|---|---:|---:|---|
| Accessibility | 1/4 | 3/4 | Main keyboard/focus/label problems pass; several AA text-contrast failures remain. |
| Performance | 2/4 | 3/4 | Initial teacher DOM is 89% smaller; full analysis data and queries remain eager. |
| Responsive design | 2/4 | 3/4 | Tablet overflow is contained and phone touch panning works; Clear targets meet AA minimum but remain below 44×44 comfort sizing. |
| Theming | 3/4 | 3/4 | Published variants agree; success and selected-state contrast still need correction. |
| Implementation integrity | 2/4 | 3/4 | Tab, modal, filtering, and status behavior agree; an isolated date-picker integration issue remains. |
| **Total** | **10/20** | **15/20** | **Good — address the remaining gaps** |

## Previous findings: verification

| Original finding | Result | Verification |
|---|---|---|
| 1. Teacher tabs | Resolved | ArrowRight selects/focuses Analysis; End selects Settings; Home selects Results. All three panel IDs exist. An unknown `tab` query falls back to Results. |
| 2. Readiness focus/name | Resolved | Actual Start attempt action opens a named dialog, focuses inside it, makes the page inert, and keeps five successive Tab presses inside. Escape restores the launcher and removes background inertness. Dialog WCAG axe scan has no violations. |
| 3. Closed mobile navigation | Resolved | Both roles skip the inert closed rail/sidebar. Opening makes main content inert; Escape closes and restores the toggle. |
| 4. Contrast | **Partial** | Amber, overdue counts, selected metadata, and helpers improve, but the measured cases below remain. |
| 5. Schedule labels | Resolved | Both alternate inputs have IDs, real label associations, `aria-labelledby`, and descriptions, including after repeated Settings tab visits. Clicking Available from focuses its visible alternate input. |
| 6. Clear target size | Resolved | Both buttons measure 36×28 CSS px and have distinct accessible names; no minimum-target axe violation remains. They satisfy the AA 24×24 minimum. |
| 7. Omitted-response keyboard access | Resolved in source | Explicit Omitted button and keyboard-enabled segments now exist. Space on a rendered answer card activates the corresponding pressed letter filter. This dataset has no omitted responses, so the Omitted control itself was not exercised live. |
| 8. Table containment | Resolved | At 768 px the main pane is 439 px wide with 439 px scroll width; the 333 px table wrapper contains its 730 px table. Student history has a named, focusable wrapper and its previous axe warning is gone. |
| 9. Class filter scope | Resolved | Changing the scoped student dropdown to All Classes performs one actual document navigation and removes the query parameter; the server scoping remains. The account has one class, so multi-class recovery is source-verified rather than demonstrated with another class. |
| 10. Hidden analysis cost | **Partial** | Hidden analysis rows no longer instantiate; duplicate initial recipient/attempt loading is skipped. Full analysis data and server work still load on every tab. |
| 11. Search empty feedback | Resolved | Unmatched assignment search displays recovery text and Reset filters; reset restores 27 teacher entries. Unmatched student search says No students match and Clear search restores 10 rows. |
| 12. Status sorting | Resolved | Rendered statuses now use the canonical resolver. Live ascending sort shows Completed, Overdue, Withdrawn in order and exposes `aria-sort="ascending"`. |
| 13. Published color semantics | Resolved | Header and sidebar both render the success variant; contrast on the selected sidebar background still needs work under finding 1 below. |

## Remaining findings

### [P1] 1. Contrast corrections do not cover all backgrounds and selected states

**Category:** Accessibility / Theming. **Locations:** [workspace CSS:15](D:/Projects/php/digital-sat/resources/css/classroom-workspace.css:15), [active counts:649](D:/Projects/php/digital-sat/resources/css/classroom-workspace.css:649), [analysis summary:541](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:541), [inspection:776](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:776), [selected Correct filter:892](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:892), [timezone:1025](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:1025), [Danger Zone:1088](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:1088).

Small text still fails WCAG 1.4.3's 4.5:1 minimum:

| Rendered state | Contrast |
|---|---:|
| Teacher selected sidebar Published, `#2f855a` on `#f0f7ff` | 4.20:1 |
| Teacher phone Published on `#f8fafc` | 4.34:1 |
| Student selected All count, white on translucent brand tint | 4.32:1 |
| Analysis module average error, emerald text on pale header | 3.49:1 |
| Inspection Correct badge and selected Correct filter, white on emerald | 3.65:1 |
| Settings timezone text on slate tint | 4.34:1 |
| Danger Zone heading on its tinted card | 4.43:1 |

**Impact:** statuses, counts, and analysis information remain harder to read for people with low vision. Correcting amber alone did not cover success colors or translucent selected backgrounds.

**Recommendation:** tune the success foreground and inverse-on-success combination, darken remaining small helper/heading colors, and measure count pills after compositing their actual selected backgrounds. Check default, selected, tinted, and active-filter states together, scoped to this product surface.

**Suggested command:** `$impeccable colorize`.

### [P2] 2. Analysis mounts lazily but its full data still loads eagerly

**Category:** Performance. **Locations:** [teacher controller:97](D:/Projects/php/digital-sat/app/Http/Controllers/Teacher/AssignmentController.php:97), [analysis service:259](D:/Projects/php/digital-sat/app/Services/AssignmentReportService.php:259), [completed responses:305](D:/Projects/php/digital-sat/app/Services/AssignmentReportService.php:305), [inline JSON:227](D:/Projects/php/digital-sat/resources/views/teacher/assignments/show.blade.php:227).

Results and Settings now contain **909 DOM elements**, down from **8,617**, and approximately **749,025 serialized markup characters**, down from **1,839,013**. Those are substantial improvements: about 89% fewer elements and 59% less markup.

However, the unconditional JSON script still contains **541,945 characters for 115 questions**, including choices, explanations, passages, and student responses. `build(..., perPage: 0)` still defaults to `includeAnalysis: true`, loads the full question relations, and fetches all completed attempts with answers before rendering Results or Settings. Opening Analysis instantiates approximately 8,625 elements. The duplicate initial results work was removed; the remaining problem is eager analysis work and data volume.

**Impact:** teachers who only need results/settings still pay for the full analysis dataset, and the work grows with completed attempts. No production latency or compressed-transfer claim is made.

**Recommendation:** fetch the analysis summary on first visit and retrieve full question/response inspection content on demand. Keep the matching panel shell and existing lazy mount. Avoid treating relocation into a JSON script as deferred data loading.

**Standard:** unnecessary hidden-feature data loading and rendering cost. **Suggested command:** `$impeccable optimize`.

### [P2] 3. Escape leaves the editable schedule picker open

**Category:** Implementation Integrity / Accessibility. **Location:** [date-picker configuration:60](D:/Projects/php/digital-sat/resources/js/app.js:60). Relevant installed dependency behavior: [Flatpickr keyboard handling:1688](D:/Projects/php/digital-sat/node_modules/flatpickr/dist/flatpickr.js:1688).

**Reproduction:** open Settings, click Available from to focus the alternate input, then press Escape. The `.flatpickr-calendar.open` element remains visible and focus stays in the input. This reproduces both on a fresh Settings load and after repeated tab visits. The open popup intercepted an attempted Clear click; clicking the Edit details heading dismisses it, after which Clear correctly empties both the hidden and visible values.

**Cause:** `allowInput: true` excludes the focused editable input from Flatpickr's built-in Escape branch. The app adds no equivalent dismissal handler.

**Impact:** the expected keyboard dismissal fails and the popup can cover nearby actions. Moving focus or clicking elsewhere provides a workaround; no keyboard trap or conformance failure is asserted from this observation alone. This is newly identified, not proven newly introduced.

**Recommendation:** handle Escape on the visible editable input to close its picker while preserving field focus and editability. Verify fresh and reinitialized instances and ensure the adjacent Clear controls remain usable.

**Standard:** expected popup keyboard dismissal and unobstructed form controls. **Suggested command:** `$impeccable harden`.

## Positive findings and recurring pattern

The major improvement is that shared components now implement their promised behavior: keyboard tabs have real panels, readiness uses the existing dialog lifecycle, navigation uses inert state, alternate inputs retain their labels, and status sorting uses displayed values. Results pagination and debounced server search remain intact. Phone table panning works using synthesized touch; no document-wide overflow appears at the tested phone/tablet widths.

The remaining pattern is incomplete coverage of runtime states: colors must be tested on their actual backgrounds, lazy UI mounting must be distinguished from lazy data loading, and third-party input behavior must be checked after replacement and reinitialization.

## Method, evidence, and limits

- Audited the same assignment and development accounts as the baseline in fresh isolated Edge/Chromium `155.0.4283.45` contexts. No assignment, attempt, or settings form was submitted. Clear tests changed browser form state only.
- Repeated the eight desktop/phone role/tab scans at 1440×1000 and 390×844. All returned HTTP 200 with no uncaught JavaScript errors. Added tablet containment at 768×1024, keyboard interaction checks, runtime date-label checks, search/sort recovery, inspection selected states, and scrolled Settings contrast checks.
- On the phone results wrapper, CDP `touchStart`/12 `touchMove`/`touchEnd` events changed `scrollLeft` from 0 to 265 px. Physical devices, Safari, and Firefox remain untested.
- A supplementary 200% root-font-size stress check showed no main-pane horizontal overflow on desktop Settings, but this is not a complete browser zoom/text-resize conformance test; several legacy pixel-sized labels do not scale with root font size.
- This sample has one student classroom and no omitted responses. Those limitations are explicit in the closure table. No complete screen-reader session, production load test, or PHP regression suite was run for this audit.
- One interaction sequence stopped at Clear because the open picker intercepted the pointer. A focused confirmation reproduced the popup behavior and completed Clear, inspection, and invalid-tab checks. This was a UI interaction failure, not an uncaught application error.

Local evidence: [teacher desktop](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-reaudit/teacher-results-1440.png), [tablet containment](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-reaudit/teacher-results-768.png), [phone touch pan](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-reaudit/teacher-table-touch-390.png), [inspection selected state](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-reaudit/teacher-inspection-1440.png). Raw results: [initial scans](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-reaudit/initial-results.json), [interactions](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-reaudit/interaction-results.json), [focused confirmation](C:/Users/minhc/.codex/visualizations/2026/10/16/01a1456a-ba30-7051-a34a-bd0d4c93b5a7/assignment-reaudit/confirmation-results.json).

## Recommended actions

1. **[P1] `$impeccable colorize`** — finish success, selected-count, analysis, and Settings contrast corrections using the measured states above.
2. **[P2] `$impeccable harden`** — close editable schedule pickers with Escape and retain focus.
3. **[P2] `$impeccable optimize`** — defer analysis data and inspection payloads, preserving the working lazy mount.
4. **`$impeccable polish`** — verify the completed changes together, including active filter colors and keyboard focus.

You can ask me to run these one at a time, all at once, or in any order you prefer. Re-run `$impeccable audit` after fixes to reassess the score.
