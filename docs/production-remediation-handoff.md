# Production remediation handoff

Use this checklist with [the production runbook](production-runbook.md) when a
reviewed remediation release is prepared. The changes are still uncommitted;
this document does not authorize a push or deployment.

## Release contents

Include the complete reviewed commit and lockfiles. The remediation touches PHP
application code, Blade templates, JavaScript, CSS, PHPUnit tests, dependency
manifests, `public/.htaccess`, and CI. Include the untracked files shown by
`git status`; do not package only the tracked diff. There are no database
migrations in this remediation, so deployment must not run schema-changing
commands.

Before packaging, record the commit hash, archive name, archive SHA-256, and
`git status --short` in the release record. Keep the previous production code
archive and the new archive until post-deploy checks pass.

## Pre-deploy gates

- CI passes Composer and NPM audits, JavaScript tests, the Vite build, PHPStan,
  and the full PHPUnit suite against MySQL 8 and `sat_app_testing`.
- Confirm the deploy archive includes `public/.htaccess` and all newly added
  files. Preserve `.env`, user uploads, logs, and other production storage.
- Back up the application files and database using the existing cPanel process.
- Review `composer.lock` and `package-lock.json` with the release; do not run a
  broad dependency update on production.

## Deploy and verify

Follow the upload, extraction, permissions, and cache commands in
`production-runbook.md`. Do not run `migrate:fresh`, `migrate:refresh`,
`db:wipe`, or seed commands on production. No schema update is expected.

After deploy, check the production site in a real browser:

- Start a test, submit the Reading and Writing section, reload the break page,
  resume early, and confirm the Math timer begins with the server-authoritative
  time. Repeat after the break expires and from a stale prior-module URL.
- Open a teacher assignment form, clear its start date, and confirm the due date
  accepts a value earlier than the cleared date. Submit an empty required
  searchable select and confirm focus and the error message are visible.
- Upload a valid raster image and SVG; confirm successful uploads render and a
  simulated or observed storage failure is reported as a failure.
- Verify `/media/<filename>.svg` and `/storage/media/<filename>.svg` return the
  sandbox CSP, `X-Content-Type-Options: nosniff`, and revalidation cache policy.
- Repeat the runbook's Apache/cPanel checks for `.env`, logs, and lockfiles and
  confirm they remain inaccessible over HTTP.
- Check `storage/logs/laravel.log`, queue health, and one completed attempt for
  new errors. Record the browser, Apache, and log results with the release.

## Rollback

If a post-deploy check fails, restore the previous application archive and
restore the previous Composer dependencies using the runbook's deployment
process. There is no schema rollback because this release adds no database
migrations. Preserve user uploads and logs while restoring code. Re-run the
Apache exposure checks and confirm a student can reopen an in-progress attempt
before closing the incident.
