# DigiSAT Product Interface Direction

Status: approved visual authority for the migrated student journey and teacher workspaces, the teacher/admin Content Suite, and the public landing and trial-selection surfaces. This replaces the prior Roboto-first and paper/binder visual directives on those surfaces. Functional requirements in older page briefs remain in force unless this document explicitly supersedes their visual treatment.

## Product register

The authenticated student and teacher product uses Plus Jakarta Sans, navy `#10234A`, canvas `#F6F8FC`, blue `#2F5BEA`, gold `#F6B93B`, secondary text `#566175`, and white surfaces. Gold marks the main active action with navy text. Blue is for links, focus, and selected controls. Destructive and warning states keep their distinct semantic colors and labels. The shared authenticated navigation rail uses this navy/blue treatment for student, teacher, and admin roles.

The public landing, trial selector, and partial trial result use the same palette and Plus Jakarta Sans in a separate `.public-site` scope. Public CTAs may start a real trial without an account. Keep demo preview content static, clearly label illustrative report data, and preserve the test engine's dedicated typography and layout.

The warmer Home is the visual reference. Other pages share its type, palette, navigation, control states, and surface language while adapting layout to the user’s task. The rollout is scoped with `.product-ui` through `x-layouts.student ui="product"` and `x-layouts.admin ui="product"`; legacy pages retain the existing presentation until migrated. Do not switch this scope into a user setting or general theme service.

## Typography and content

Use installed Plus Jakarta Sans weights 400–800 for product UI. Page titles are 30–32px, guided task headings 26px, section headings 18–20px, body and standard controls 16px, and supporting or compact table text 14px. Use rem sizes, unitless line heights, clear weight steps, and tabular numerals for scores and measurements. Use monospace only for codes or technical strings.

Keep question passages, answer choices, mathematical content, and the exam’s reading typography on their existing dedicated faces. Reading explanations should stay near 65–75 characters per line; analytical layouts may use more width.

## Layout and density

Choose composition from available content width after navigation and any contextual list:

- Guided pages use a leading-aligned column capped near 1120px; supporting content joins only above 960px available width.
- Working pages use the available workspace with bounded gutters and collapse local side panels before task content becomes cramped.
- Reading content uses a 65–75ch measure, with charts and analysis allowed wider sibling regions.

Preserve DOM order on narrow screens. Let titles wrap, use `min-width: 0` and `minmax(0, 1fr)`, and keep primary controls discoverable without horizontal page scrolling. Phone inputs remain at least 16px. Preserve local overflow only for genuinely two-dimensional tables, with a visible cue and keyboard access.

## Components and states

Use the existing `<x-ui.button>`, `<x-ui.card>`, status, alert, modal, dropdown, and shell components. Primary is gold with navy foreground in the product scope; secondary is outlined and quiet; destructive actions remain distinct. Repeated row actions are secondary. Keep existing component props and native link/button semantics.

Surfaces use white, a subtle structural border, 12–16px corner radii, and minimal offset shadows. Do not nest decorative card shells. Focus indicators must work on light and navy surfaces. Forms show labels, connected hints, inline errors, and stable success/failure feedback. Loading and disabled controls retain their accessible names and prevent duplicate activation. Status must remain understandable without color.

Use short transitions for purposeful state changes, guard hover styling by pointer capability where appropriate, and honor `prefers-reduced-motion`. Browser selection, caret, scrollbars, focus, and numeric alignment are part of the product presentation.

## Boundaries and invariants

The classroom paper, binder, corkboard, pin, tape, and handwritten decoration is retired on migrated student and teacher pages. Preserve the classroom’s actual notes, materials, announcements, classmates, settings, assignment eligibility, deadlines, and navigation. Do not remove functional content with decorative wrappers.

The exam engine remains isolated from product-surface styling: no engine palette, passage typography, renderer, timer, submission, or scoring behavior is changed. Public-trial navigation may enter the existing engine through the static demo and marked practice attempts. The Content Suite uses the product presentation for both teachers and admins while retaining its authoring navigation. Authentication uses the approved product identity through its own `.auth-body` token scope, separate from `.product-ui`; remaining legacy marketing surfaces stay outside the migration. Shared reports use the same presentation across roles; viewer permissions and teacher/student actions remain distinct. Print/PDF layouts retain their export-specific typography and geometry.

Preserve Home’s authoritative next-action priorities, guide and goal behavior, score basis/disclosures, history data, assignment constraints, ownership checks, and existing readiness flows. Styling must not invent scores, eligibility, personalization, mastery, or duration claims.

## Rollout

Student delivery: Home → Test Library and Preview information → Classes and Assignments → Score Report → Progress analytics.

Teacher delivery: Home and personal practice → Classes and classroom management → Assignments/results/analysis/settings → student progress and shared score reports → live exam management → Content Suite for teachers and admins. All use the same tokens and component states. Teacher tables and metadata use 14px text; standard controls/body use 16px. Workspace density changes spacing and arrangement, never the visual identity.

Keep the default legacy layout only for remaining unreviewed consumers: Blog, Forum, Profile, Notifications and admin Teacher Applications. Public marketing uses `.public-site`, authentication uses `.auth-body`, and authenticated product views use `.product-ui`. Remove unused teacher-specific legacy rules and duplicated chart theme branches now. Shared legacy classroom rules stay until their remaining consumers migrate; protect the exam engine and shared question-content typography throughout.
