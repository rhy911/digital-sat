<?php

namespace App\Livewire\Teacher;

use App\Models\ExamSession;
use App\Models\UserTest;
use App\Services\ExamSessionService;
use Livewire\Component;

/**
 * Live tracker for one candidate's attempt, polled every 5s.
 *
 * Only the figures and the question map live in here. The question-detail modal
 * stays in the surrounding Blade page on purpose: it holds Alpine state and
 * fetched HTML, and keeping it outside the component means Livewire's DOM morph
 * can never blow away a modal the teacher is reading mid-poll.
 */
class ExamAttemptMonitor extends Component
{
    public ExamSession $examSession;

    public UserTest $attempt;

    public function mount(ExamSession $examSession, UserTest $attempt): void
    {
        $this->examSession = $examSession;
        $this->attempt = $attempt;

        $this->assertViewable();
    }

    /**
     * Re-checked on every render, not just on mount.
     *
     * A Livewire component is re-hydrated from a client-supplied payload and its
     * update endpoint is directly callable, so the ids arriving on a poll are
     * attacker-controlled input — authorising once in mount() would leave the
     * poll itself unguarded.
     */
    private function assertViewable(): void
    {
        $this->authorize('view', $this->examSession);
        abort_unless((int) $this->attempt->exam_session_id === (int) $this->examSession->id, 404);
    }

    public function render(ExamSessionService $service)
    {
        $this->assertViewable();

        $this->attempt->load([
            'user',
            'currentModule.section',
            'moduleSubmissions',
            'userAnswers',
        ]);

        return view('livewire.teacher.exam-attempt-monitor', [
            'attempt' => $this->attempt,
            'examSession' => $this->examSession,
            'moduleGrid' => $service->questionGrid($this->attempt),
        ]);
    }
}
