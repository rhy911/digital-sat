<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module submission timing
    |--------------------------------------------------------------------------
    |
    | These four numbers are a single interlocking set. A 70-student mock exam
    | produced `module_progression_conflict` and `Scoring timeout` popups
    | precisely because they were not coherent (client 125s / lock 90s / job
    | timeout 60s / retry_after 90s). Do not tune one in isolation — every
    | invariant below has to keep holding.
    |
    | I1 (Safety)              retry_after (180s) > job_timeout (120s)
    |     Otherwise the database queue re-reserves a job that is still running
    |     and executes it a second time. retry_after lives in config/queue.php.
    |
    | I2 (Conflict protection) lock_ttl >= max_queue_wait + job_timeout
    |     If the lock lapses while its job is still queued, a resubmit passes
    |     every check and dispatches a DUPLICATE job. That is the incident.
    |         max_queue_wait = terminal_submits x median_job_seconds / workers
    |     At 70 students on 2 workers this is TIGHT:
    |         median 2s -> needs 190s   OK
    |         median 3s -> needs 225s   marginal
    |         median 4s -> needs 260s   NOT ENOUGH at 240
    |     Re-derive from the `duration_ms` values logged by ScoreModuleJob after
    |     the next load test. If p50 >= 3s, raise lock_ttl (and client_budget
    |     with it, to preserve I3) or add a third worker.
    |
    | I3 (Client patience)     client_budget (300s) > lock_ttl (240s)
    |     Must be STRICTLY greater, never equal. If they matched, the client
    |     would give up at the exact moment the lock expires; a student pressing
    |     Next right then would acquire the just-released lock and dispatch a
    |     duplicate job. The 60s gap is the safety margin.
    |
    | I4 (Result persistence)  result_ttl (900s) > client_budget (300s)
    |     A student who waits out the whole budget, or reloads, must still find
    |     the result.
    |
    */

    'job_timeout' => (int) env('SCORING_JOB_TIMEOUT', 120),

    'lock_ttl' => (int) env('SCORING_LOCK_TTL', 240),

    'result_ttl' => (int) env('SCORING_RESULT_TTL', 900),

    'client_budget' => (int) env('SCORING_CLIENT_BUDGET', 300),

    /*
    |--------------------------------------------------------------------------
    | Inline scoring for non-terminal modules
    |--------------------------------------------------------------------------
    |
    | Routing after a non-terminal module is a single theta estimate over ~27
    | items — cheap enough to run inside the request. The queue only earns its
    | keep on the terminal submission, where finalize() runs two IRT estimates
    | plus conversion plus a possible auto-merge.
    |
    | This is a kill switch: flip it off to force every submission back onto the
    | queue mid-exam without a deploy. Any exception raised while scoring inline
    | must also fall back to dispatching the job — never to an error screen.
    |
    */

    'inline_non_final' => (bool) env('SCORING_INLINE_NON_FINAL', true),

];
