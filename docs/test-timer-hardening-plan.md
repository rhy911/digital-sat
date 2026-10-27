# Test Timer Hardening Plan

## Behavior

- Assigned exams and live exam sessions use one server-authoritative continuous schedule. Module deadlines, scheduled breaks, and later modules continue while the student is disconnected, the browser is closed, the device sleeps, or a tab is hidden.
- The server accepts answer changes only while the active module deadline has not passed. At expiry, it submits the answers already stored by autosave and marks unanswered questions omitted. Client countdowns only display the server clock and trigger a submit request; they do not establish a deadline.
- Practice, including guest trials, pauses when the tab is hidden, the browser is closed, or the connection is lost. Practice answers are covered during a pause and become editable again only after reconnection and an explicit Resume. After a timed module or scheduled break, the next module waits for an explicit Continue, including when a break has zero remaining time.
- A single control token owns a live attempt. Another tab/device sees an ownership screen and must explicitly take over. Old writes fail after takeover.
- Teachers can create only continuous assignments. Legacy paused assignments convert to continuous clocks while preserving their saved elapsed time or scheduled-break deadline. The normal expiry sweep then catches up overdue modules.

## Server and browser ownership

`AttemptEngine` owns clock calculation, answer checkpointing, pause/resume, control leases, submission deadline checks, and overdue progression. HTTP mutations require the attempt-controller token. The browser uses a monotonic countdown for display, synchronizes continuous attempts with the timing endpoint on wake, sends practice heartbeats/checkpoints while visible, and asks the server to pause practice on hide, disconnect, or page exit. The server pauses practice when a controller lease goes stale.

## Legacy assignment conversion

Apply the additive attempt-control migration through the normal maintenance deployment. Before reopening student traffic, run `php artisan assignments:convert-paused-clocks --dry-run --max-assignments=100` against the target database and inspect the counts. Then run the command without `--dry-run` in bounded batches until it reports no remaining assignments. The conversion is atomic per assignment and safe to repeat. For each active attempt it anchors to its active module start minus saved elapsed time, or, when no module clock is active, to the scheduled end of an issued section break or the attempt creation time. A resumed module clock takes precedence over an older section-break receipt. The conversion resets the paused elapsed counter, clears the pause flag, and changes the assignment clock mode to continuous. Completed attempts and their snapshots remain unchanged.

After conversion, run `php artisan assignments:finalize-expired --dry-run`, inspect overdue attempts, then run `php artisan assignments:finalize-expired` before normal student traffic resumes. Keep the existing once-a-minute schedule enabled for subsequent expiry and progression.

## Regression checks

- Route-level PHP feature tests cover server elapsed-time checkpoints, rejection of early client timeouts, controller ownership and takeover, saved practice pause/resume state, legacy conversion, and overdue cascading.
- Node module tests cover monotonic countdown behavior, server resynchronization, stale heartbeat replies after token rotation, paused practice not starting a local clock, explicit Continue after practice timeout/break, and storage failures not blocking autosave.
- Also run scoped PHPStan/Pint, the full JavaScript test set, and `npm run build`. Do not run a migration against the local application database as part of verification; PHPUnit supplies the isolated `sat_app_testing` connection.
