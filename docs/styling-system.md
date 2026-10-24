# Styling system

This file documents implementation boundaries for the approved shared product direction in [`DESIGN.md`](../DESIGN.md). The student and teacher migration uses a `.product-ui` scope, semantic `--ds-*` roles, and the existing Blade UI and shell components. See [`docs/warm-product-ui-implementation-plan.md`](warm-product-ui-implementation-plan.md) for rollout order and acceptance criteria.

## Active migration rules

- `components/layouts/student.blade.php` accepts `ui="product"`; the default stays on the legacy presentation. Explicitly migrated student and teacher views opt in. The Content Suite opts in through `components/layouts/admin.blade.php` for both author roles.
- `resources/css/product-ui.css` is imported once by `resources/css/app.css`. Page-surface selectors are scoped under `.product-ui`; shared authenticated navigation uses `.icon-rail--product` across student, teacher, and admin roles. The product surface must not alter the test engine or marketing pages.
- `resources/css/design-tokens.css` remains the token source. Product scope maps the existing semantic roles to the approved navy, canvas, blue, gold and neutral palette. Brand/link blue and action gold are separate roles.
- `x-ui.button` and `x-ui.card` keep their current public API. Product scope changes appearance through scoped roles; unopted consumers retain the legacy result.
- Use 16px body/control text, 14px supporting/compact text, Jakarta Sans, tabular numerals, and task-specific guided/workspace/reading widths.
- Classroom decoration is removed from opted-in student and teacher views. Preserve all functional LMS information and behavior.
- `components/layouts/test.blade.php`, `resources/views/engine/**`, engine CSS/JS, question rendering, exam timing and score calculation remain protected.

## Historical notes

Earlier sections of this document described a global Roboto-first presentation, a Home-only exception, and a Manila/paper classroom visual language. Those visual decisions are superseded for migrated student and teacher workflows by `DESIGN.md`. Historical feature contracts, icon and math-renderer notes, accessibility rules, and route behavior remain useful unless they conflict with the active migration rules above.

## Teacher workspace adoption

- Teacher Home/practice, Classes, Assignments and inspection dialogs, student diagnostics, shared score reports and exam management use the same `.product-ui` foundation as student pages. `.teacher-workspace` adapts analytical width and responsive arrangement; it is not a separate theme.
- Content Suite retains its authoring navigation and existing tab IDs, modal hooks, libraries, editor behavior and question rendering. Its stylesheet maps local structural aliases to product tokens instead of defining a competing global palette or Roboto body font.
- The progress stylesheet and chart renderer have one shared theme. Remove duplicate legacy/product declarations rather than adding another teacher override layer.
- Standard controls/body use 16px; tables and supporting labels use 14px. Preserve real notes and all classroom content while removing notebook/dashed-paper decoration from inspection surfaces.
- Keep existing access rules, ownership checks, polling, exports, timing and scoring behavior. Print/PDF remain export-specific; the engine is isolated.
- Active legacy classroom consumers still include Blog, Forum, Profile, Notifications and Teacher Applications. Retain their shared rules until reviewed; remove only confirmed-unused styling and replaced teacher-specific presentation.
