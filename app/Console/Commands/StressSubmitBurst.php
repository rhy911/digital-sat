<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserTest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Fire every seeded student's module submission inside the same second, then poll
 * each one to a result — the synchronized burst that a real mock exam produces and
 * that a ramped load test does not.
 *
 * Pair with sat:seed-stress-cohort, which parks the cohort on one module.
 *
 * Sessions are built in-process rather than by logging in over HTTP: POST /signin
 * carries throttle:5,1 per IP, so 70 real logins from one machine would take 14
 * minutes and mostly 429. Building the session directly is also closer to reality —
 * in a real exam the students are already logged in when the timer expires.
 */
class StressSubmitBurst extends Command
{
    protected $signature = 'sat:stress-submit
        {--prefix=stress : Email prefix used by sat:seed-stress-cohort}
        {--url= : Base URL, defaults to config(app.url)}
        {--poll-timeout=180 : Seconds to keep polling before giving up on a student}
        {--concurrency=100 : Max simultaneous in-flight requests}';

    protected $description = 'Fire a synchronized concurrent module-submit burst against a seeded cohort (local/staging only).';

    public function handle(): int
    {
        if (! app()->environment(['local', 'staging'])) {
            $this->error('Refusing to run outside local/staging. Current environment: '.app()->environment());

            return self::FAILURE;
        }

        $base = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $prefix = (string) $this->option('prefix');

        $cohort = $this->buildCohort($prefix);
        if ($cohort->isEmpty()) {
            $this->error("No seeded students found with prefix '{$prefix}'. Run sat:seed-stress-cohort first.");

            return self::FAILURE;
        }

        $this->info("Base URL : {$base}");
        $this->info('Students : '.$cohort->count());
        $this->newLine();

        $client = new Client([
            'http_errors' => false,
            'timeout' => 120,
            'verify' => false,
        ]);

        // ---- Phase 1: the burst -------------------------------------------------
        $this->info('Firing submit burst...');
        $results = [];
        $burstStart = microtime(true);

        $requests = function () use ($cohort, $base) {
            foreach ($cohort as $row) {
                yield new Request(
                    'POST',
                    "{$base}/engine/test/submit-module",
                    [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-CSRF-TOKEN' => $row['csrf'],
                        'Cookie' => $row['cookie'],
                    ],
                    json_encode([
                        'user_test_id' => $row['user_test_id'],
                        'module_id' => $row['module_id'],
                        'answers' => $row['answers'],
                    ])
                );
            }
        };

        $keys = $cohort->values();

        $pool = new Pool($client, $requests(), [
            'concurrency' => (int) $this->option('concurrency'),
            'fulfilled' => function ($response, $index) use (&$results, $keys, $burstStart) {
                $raw = (string) $response->getBody();
                $body = json_decode($raw, true) ?: [];
                $results[$index] = [
                    'row' => $keys[$index],
                    'post_status' => $response->getStatusCode(),
                    'post_body' => mb_substr($raw, 0, 300),
                    'post_error' => $body['error'] ?? null,
                    'settled' => ! empty($body['status']) && $body['status'] !== 'scoring',
                    'elapsed' => microtime(true) - $burstStart,
                    'polls' => 0,
                ];
            },
            'rejected' => function ($reason, $index) use (&$results, $keys, $burstStart) {
                $results[$index] = [
                    'row' => $keys[$index],
                    'post_status' => 0,
                    'post_error' => $reason instanceof RequestException ? 'transport' : 'unknown',
                    'settled' => false,
                    'elapsed' => microtime(true) - $burstStart,
                    'polls' => 0,
                ];
            },
        ]);

        $pool->promise()->wait();
        $burstSpread = microtime(true) - $burstStart;
        $this->line(sprintf('All %d POSTs completed within %.2fs.', count($results), $burstSpread));

        // ---- Phase 2: poll the stragglers ---------------------------------------
        $deadline = microtime(true) + (int) $this->option('poll-timeout');
        $this->info('Polling for results...');

        while (microtime(true) < $deadline) {
            $outstanding = array_filter($results, fn ($r) => ! $r['settled']);
            if (! $outstanding) {
                break;
            }

            usleep(1_500_000);

            foreach ($outstanding as $index => $r) {
                $row = $r['row'];
                $response = $client->get(
                    "{$base}/engine/submit-status/{$row['attempt_ulid']}?module_id={$row['module_id']}",
                    ['headers' => ['Accept' => 'application/json', 'Cookie' => $row['cookie']]]
                );
                $body = json_decode((string) $response->getBody(), true) ?: [];
                $results[$index]['polls']++;

                if (($body['status'] ?? 'scoring') !== 'scoring') {
                    $results[$index]['settled'] = true;
                    $results[$index]['elapsed'] = microtime(true) - $burstStart;
                }
            }

            $this->line(sprintf('  %d/%d settled...', count($results) - count($outstanding), count($results)));
        }

        return $this->report($results, $burstSpread);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function report(array $results, float $burstSpread): int
    {
        $times = collect($results)->filter(fn ($r) => $r['settled'])->pluck('elapsed')->sort()->values();
        $conflicts = collect($results)->filter(fn ($r) => $r['post_error'] === 'module_progression_conflict')->count();
        $inProgress = collect($results)->filter(fn ($r) => $r['post_error'] === 'submission_in_progress')->count();
        $unsettled = collect($results)->filter(fn ($r) => ! $r['settled'])->count();
        $transport = collect($results)->filter(fn ($r) => $r['post_status'] === 0)->count();

        $pct = fn ($p) => $times->isEmpty() ? 0.0 : $times[(int) floor(($times->count() - 1) * $p)];

        $this->newLine();
        $this->table(['metric', 'value'], [
            ['students', count($results)],
            ['burst spread (s)', sprintf('%.2f', $burstSpread)],
            ['settled', $times->count()],
            ['NEVER settled', $unsettled],
            ['p50 time-to-result (s)', sprintf('%.2f', $pct(0.50))],
            ['p95 time-to-result (s)', sprintf('%.2f', $pct(0.95))],
            ['max time-to-result (s)', sprintf('%.2f', $times->isEmpty() ? 0 : $times->last())],
            ['409 module_progression_conflict', $conflicts],
            ['409 submission_in_progress', $inProgress],
            ['transport failures', $transport],
        ]);

        $byStatus = collect($results)->countBy('post_status')->map(fn ($n, $s) => "{$s}:{$n}")->implode('  ');
        $this->line('POST status codes: '.$byStatus);

        // When nothing settles the cause is almost always the request never being
        // authenticated, which looks identical to "still scoring" from out here.
        if ($unsettled === count($results) && $unsettled > 0) {
            $first = collect($results)->first();
            $this->newLine();
            $this->warn('Nothing settled — first POST response body:');
            $this->line($first['post_body'] ?? '(empty)');
        }

        $this->newLine();
        $this->line('Acceptance gate:');
        $this->line(sprintf('  %s 0 module_progression_conflict        (got %d)', $conflicts === 0 ? 'PASS' : 'FAIL', $conflicts));
        $this->line(sprintf('  %s 0 students never settled             (got %d)', $unsettled === 0 ? 'PASS' : 'FAIL', $unsettled));
        $this->line(sprintf('  %s max time-to-result < 120s            (got %.1fs)', ($times->last() ?? 0) < 120 ? 'PASS' : 'FAIL', $times->last() ?? 0));
        $this->newLine();
        $this->line('Job timings: grep score_module_job storage/logs/queue-*.log');
        $this->line('`submission_in_progress` is NOT a failure — it means the client was told to keep polling.');

        return ($conflicts === 0 && $unsettled === 0) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Build one authenticated session per seeded student, plus the answer payload
     * for the module they are parked on.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildCohort(string $prefix): \Illuminate\Support\Collection
    {
        $students = User::where('email', 'like', $prefix.'%@example.test')->orderBy('id')->get();
        $cohort = collect();

        foreach ($students as $student) {
            $attempt = UserTest::where('user_id', $student->id)
                ->where('status', 'in_progress')
                ->latest('id')
                ->first();

            if (! $attempt || ! $attempt->current_module_id) {
                continue;
            }

            $module = $attempt->currentModule()->with('questions.answerChoices')->first();
            if (! $module) {
                continue;
            }

            $answers = [];
            foreach ($module->questions as $question) {
                $correct = $question->answerChoices->firstWhere('is_correct', true);
                $answers[(string) $question->id] = $correct->label ?? 'A';
            }

            [$cookie, $csrf] = $this->authenticatedSession($student);

            $cohort->push([
                'user_test_id' => $attempt->id,
                'attempt_ulid' => $attempt->ulid,
                'module_id' => $module->id,
                'answers' => $answers,
                'cookie' => $cookie,
                'csrf' => $csrf,
            ]);
        }

        return $cohort;
    }

    /**
     * Mint a real server-side session for this user and return the encrypted
     * cookie header plus its CSRF token. This is exactly what the browser would
     * hold after logging in — minus the throttled round trip.
     *
     * @return array{0: string, 1: string}
     */
    private function authenticatedSession(User $user): array
    {
        // A FRESH manager — and therefore a fresh Store AND a fresh handler — per
        // student. Both are stateful in ways that break batch use:
        //   Store::start() MERGES loaded data into whatever attributes the instance
        //     already holds, leaking the previous student's CSRF token forward.
        //   DatabaseSessionHandler tracks an $exists flag, so after the first
        //     write it switches to UPDATE and silently writes nothing for every
        //     session after that — the server then issues a brand new session and
        //     the request 419s on CSRF.
        // Symptom of getting this wrong: exactly one student succeeds.
        $session = (new SessionManager($this->laravel))->driver();
        $session->setId(Str::random(40));
        $session->start();
        // Same key SessionGuard uses: 'login_web_'.sha1(class).
        $session->put('login_web_'.sha1(\Illuminate\Auth\SessionGuard::class), $user->getAuthIdentifier());
        $session->put('password_hash_web', $user->getAuthPassword());
        $session->regenerateToken();
        $token = $session->token();
        $session->save();

        $name = (string) config('session.cookie');

        // EncryptCookies prefixes every cookie value with an HMAC of its own name
        // before encrypting, so a bare Crypt::encrypt(sessionId) decrypts fine but
        // then fails prefix validation and the request arrives unauthenticated.
        $prefixed = CookieValuePrefix::create($name, Crypt::getKey()).$session->getId();

        return [$name.'='.rawurlencode(Crypt::encrypt($prefixed, false)), $token];
    }
}
