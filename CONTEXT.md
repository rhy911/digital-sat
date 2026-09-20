# Domain Glossary (CONTEXT.md)

- **Test**: The exam specification and container for sections and modules (`adaptive_full`, `normal_full`, `section_only`, `module_only`).
- **Section**: Top-level subject partition (`reading_writing` or `math`).
- **Module**: Discrete 27-question (RW) or 22-question (Math) test module.
- **Attempt (`UserTest`)**: A student's sitting of a test.
  - **Chained Clock**: Continuous timer for assignments and live exam sessions. The next module starts when the previous deadline passes, even if the student leaves.
  - **Paused Clock**: Pausable timer for student self-study practice.
- **Submission**: Finalizing a module, saving question snapshots, grading answers, and advancing the attempt.
- **Cascade**: Sweeper process that automatically advances overdue modules on chained-clock attempts when a student is away.
