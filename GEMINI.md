# Digital SAT Online Testing System

E-learning platform. Mimics Bluebook. LMS for progress/admin.

## 1. Modules

### A. Identity & Access
- **Auth:** Login/Register/Verify/Reset. Laravel Sanctum/Fortify.
- **Roles:** Student (test), Teacher (manage/track), Admin (system/config).

### B. Student Portal
- **Dash:** Progress, tests, recommendations.
- **Library:** Full-length + modular practice.
- **Review:** History, analytics, domain performance, explanations.

### C. Test Engine (Bluebook Clone)
- **UI:** Exact Bluebook replica.
- **Adaptive:** Theta-based M2 routing.
- **Tools:** Timer, Calc (Desmos), Strike, Highlight, Review Grid.
- **Security:** Lockdown browser mock (block copy/paste/right-click).

### E. Scoring (Advanced IRT)
- **Model:** 3PL IRT.
- **Params:** Difficulty (b), Discrimination (a), Guessing (c).
- **Process:** Filter `is_pretest`. MLE (Newton-Raphson) for Theta [-4, 4]. Sigmoid mapping to 200-800.
- **Routing:** M1 Theta decide M2 Easy/Hard.

## 2. Dev Standards

### Backend (Laravel 11)
- MVC + Service Layer. Business logic in `app/Services`.
- `FormRequest` for validation.
- Security: CSRF, SQLi protection, Rate limiting.

### Frontend (Blade + JS)
- Asset: Vite.
- **JS:** Vanilla JS for Test Engine.
- **Styling:** Hybrid Tailwind v4 + Raw CSS.
  - **Raw CSS:** Complex (transitions, animations, custom gradients, shadows, hover).
  - **Tailwind:** Utility (layout, spacing, flex, grid, colors). No Bootstrap 5 for new parts.

### DB (MySQL)
- High normalization.
- Index `external_id`, `status`, `test_type`.
- Soft Delete for Test/Section/Module cascade.

## 3. Agent/Dev Workflow (CRITICAL)

- **Surgical Edit:** Focus on task. No broad refactor unless asked.
- **Session Memory:** Add log to `prompts/agent_memory.md`. **MUST** use `caveman-compress` for log.
- **Feature Tracking:** Update `prompts/feature_memory.md` for logic changes.
- **Naming:** PSR-12 (PHP), CamelCase (JS). Use new Laravel features.
- **Validation:** ALWAYS run tests. Logic + Schema.
- **Proactive Plan:** If complex, use `enter_plan_mode` first.

## 4. Naming Conventions

- **PHP:** `PascalCase` classes/controllers. `camelCase` methods/vars.
- **JS:** `camelCase` functions/vars. `UPPER_SNAKE_CASE` constants. `kebab-case.js` files.
- **CSS:** `kebab-case` classes. BEM for custom components.
- **DB:** `snake_case` tables (plural) / columns (singular). `singular_table_id` FKs.
- **Views:** `kebab-case.blade.php`. `snake_case` images.
- **API:** `kebab-case` routes. `snake_case` params.

## 5. Caveman Mode (ALWAYS ON)

Agent MUST apply rules from `.agents/skills/caveman/SKILL.md`.
- Default Intensity: `full`.
- Persistent every turn.
- Terse prose, exact tech.
