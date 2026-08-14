@php
    $user = auth()->user();
    $joinUrl = url('/exam-join/'.$examSession->code);

    // Counted off the already-loaded roster, so the summary can never disagree
    // with the list rendered underneath it.
    $inProgressCount = $candidates->where('status', 'in_progress')->count();
    $completedCount = $candidates->where('status', 'completed')->count();
    $scored = $candidates->whereNotNull('total_score');
    $averageScore = $scored->isNotEmpty() ? round($scored->avg('total_score')) : null;

    $statusOptions = [
        'active' => 'Active',
        'paused' => 'Paused',
        'closed' => 'Closed',
    ];
@endphp

<x-layouts.student :user="$user" title="{{ $examSession->title }}" header-type="none">
    @push('styles')
        {{-- Same trio /home loads: classroom-workspace.css owns the app shell
             (.app-shell/.shell-content), analytics.css the shared `.home*`
             dashboard vocabulary, and exam-sessions.css only what /home lacks. --}}
        @vite([
            'resources/css/classroom-workspace.css',
            'resources/css/student/analytics.css',
            'resources/css/teacher/exam-sessions.css',
        ])
    @endpush

    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::teacher('exam-sessions')" />

        <main class="shell-content">
            <x-ui.flash />

            <div class="home">
                <header class="home-head">
                    <div class="home-head__lead">
                        <div class="home-head__meta">
                            <a href="{{ route('teacher.exam-sessions.index') }}" class="home-link">
                                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                                All exam sessions
                            </a>
                            <x-ui.status-badge
                                :status="match ($examSession->status) {
                                    'active' => 'success',
                                    'paused' => 'warning',
                                    default => 'neutral',
                                }">
                                {{ ucfirst($examSession->status) }}
                            </x-ui.status-badge>
                        </div>
                        <h1 class="home-title">{{ $examSession->title }}</h1>
                        <p class="home-subtitle">
                            {{ $examSession->test->title }}
                            @if ($examSession->expires_at)
                                &middot; {{ $examSession->isExpired() ? 'Expired' : 'Expires' }}
                                {{ $examSession->expires_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>

                    <div class="home-head__actions">
                        <x-ui.button size="sm" variant="secondary"
                            :href="route('teacher.exam-sessions.export.csv', $examSession)">
                            <x-slot:icon><x-ui.icon name="download" class="w-4 h-4" /></x-slot:icon>
                            Export CSV
                        </x-ui.button>
                    </div>
                </header>

                <x-ui.card aria-labelledby="share-code-title">
                    <div class="exam-hero">
                        <div class="exam-hero__lead">
                            <p class="exam-hero__label" id="share-code-title">Share code</p>
                            <span class="exam-hero__code">{{ $examSession->code }}</span>
                            <span class="exam-hero__url">{{ $joinUrl }}</span>
                        </div>

                        <div class="exam-hero__actions">
                            <x-ui.button size="sm" variant="secondary" id="copyJoinLink"
                                data-join-url="{{ $joinUrl }}" data-label="Copy link">
                                <x-slot:icon><x-ui.icon name="copy" class="w-4 h-4" /></x-slot:icon>
                                <span data-copy-label>Copy link</span>
                            </x-ui.button>

                            <x-ui.button size="sm" variant="secondary" id="showQrCode">
                                <x-slot:icon><x-ui.icon name="grid-3x3-gap-fill" class="w-4 h-4" /></x-slot:icon>
                                Show QR
                            </x-ui.button>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card :padded="false" class="home-stats" role="group" aria-label="Session at a glance">
                    <div class="home-stats__grid">
                        <div class="home-stat">
                            <span class="home-stat__label">Candidates</span>
                            <span class="home-stat__value home-num" id="candidateCount">{{ $candidates->count() }}</span>
                        </div>

                        <div class="home-stat">
                            <span class="home-stat__label">In progress</span>
                            <span class="home-stat__value home-num" id="inProgressCount">{{ $inProgressCount }}</span>
                        </div>

                        <div class="home-stat">
                            <span class="home-stat__label">Completed</span>
                            <span class="home-stat__value home-num" id="completedCount">{{ $completedCount }}</span>
                        </div>

                        <div class="home-stat">
                            <span class="home-stat__label">Average total</span>
                            <span class="home-stat__value home-num" id="averageScore">{{ $averageScore ?? '—' }}</span>
                            <span class="home-stat__note">Scored attempts only</span>
                        </div>
                    </div>
                </x-ui.card>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-8">
                        <x-ui.card stretch aria-labelledby="candidates-title">
                            <x-slot:header>
                                <div class="home-panel__headline">
                                    <h2 id="candidates-title" class="home-panel__title">Candidates</h2>
                                    <p class="home-panel__sub">Live roster and scores</p>
                                </div>
                                <span class="exam-live">
                                    <span class="exam-live__dot" aria-hidden="true"></span>
                                    Updating every 4s
                                </span>
                            </x-slot:header>

                            <div id="candidateList" aria-live="polite">
                                @include('teacher.exam-sessions.partials.candidate-rows')
                            </div>
                        </x-ui.card>
                    </div>

                    <div class="lg:col-span-4">
                        <x-ui.card stretch aria-labelledby="session-state-title">
                            <x-slot:header>
                                <div class="home-panel__headline">
                                    <h2 id="session-state-title" class="home-panel__title">Session state</h2>
                                    <p class="home-panel__sub">Applies to new joins immediately</p>
                                </div>
                            </x-slot:header>

                            {{-- Three submit buttons in one form rather than a select + onchange:
                                 the current state stays readable at a glance, and it works with
                                 JS off. Deliberately no data-confirm — app.js's confirm handler
                                 resubmits the form itself, which drops the clicked submitter and
                                 with it the status value. --}}
                            <form method="POST" action="{{ route('teacher.exam-sessions.status.update', $examSession) }}">
                                @csrf
                                @method('PUT')
                                <div class="home-switch" role="group" aria-label="Session status">
                                    @foreach ($statusOptions as $value => $label)
                                        <button type="submit" name="status" value="{{ $value }}"
                                            data-status="{{ $value }}"
                                            class="home-switch__btn exam-switch__btn {{ $examSession->status === $value ? 'is-active' : '' }}"
                                            @if ($examSession->status === $value) aria-current="true" @endif>
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </form>

                            <p class="home-note">
                                @switch($examSession->status)
                                    @case('active')
                                        Anyone with the code can join and start.
                                        @break
                                    @case('paused')
                                        New candidates are turned away. Anyone already taking the exam keeps
                                        going — their clock does not stop.
                                        @break
                                    @default
                                        The session is closed to new candidates.
                                @endswitch
                            </p>

                            <x-slot:footer>
                                <div class="home-panel__foot">
                                    <span>Exam clock runs server-side</span>
                                </div>
                            </x-slot:footer>
                        </x-ui.card>
                    </div>
                </div>
            </div>
        </main>
    </div>

    {{-- QR dialog --}}
    <div id="qrModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        role="dialog" aria-modal="true" aria-labelledby="qrModalTitle">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
            <h2 id="qrModalTitle" class="home-panel__title">Scan to join</h2>
            <p class="home-panel__sub mb-4">Point a phone or tablet camera at the code</p>

            <div class="exam-qr__frame mb-4">
                <img class="exam-qr__img"
                    src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($joinUrl) }}"
                    alt="QR code linking to {{ $joinUrl }}">
            </div>

            <p class="exam-qr__code mb-4">{{ $examSession->code }}</p>

            <x-ui.button id="closeQrCode" size="sm" variant="secondary" class="w-full">Close</x-ui.button>
        </div>
    </div>

    <script>
        (function () {
            const liveStatusUrl = @json(route('teacher.exam-sessions.live-status', $examSession));
            const list = document.getElementById('candidateList');
            const qrModal = document.getElementById('qrModal');
            const copyBtn = document.getElementById('copyJoinLink');

            // --- Copy link -------------------------------------------------
            // navigator.clipboard only exists in a secure context (HTTPS or
            // localhost). Local dev runs on http://digital-sat.test, which is
            // neither, so the API is undefined there and the promise path throws
            // before it ever copies — hence the execCommand fallback, which
            // works on plain HTTP.
            async function copyText(text) {
                if (navigator.clipboard?.writeText) {
                    try {
                        await navigator.clipboard.writeText(text);
                        return true;
                    } catch {
                        // Fall through: permission denied, or a browser that
                        // exposes the API but refuses it on this origin.
                    }
                }

                const scratch = document.createElement('textarea');
                scratch.value = text;
                scratch.setAttribute('readonly', '');
                // Off-screen rather than display:none — execCommand('copy')
                // needs the node to be selectable.
                scratch.style.position = 'fixed';
                scratch.style.top = '-9999px';
                document.body.appendChild(scratch);
                scratch.select();

                let ok = false;
                try {
                    ok = document.execCommand('copy');
                } catch {
                    ok = false;
                }

                scratch.remove();

                return ok;
            }

            copyBtn?.addEventListener('click', async () => {
                const label = copyBtn.querySelector('[data-copy-label]');
                const copied = await copyText(copyBtn.dataset.joinUrl);

                label.textContent = copied ? 'Copied' : 'Copy failed';
                setTimeout(() => { label.textContent = copyBtn.dataset.label; }, 2000);
            });

            // --- QR dialog -------------------------------------------------
            const openQr = () => qrModal.classList.replace('hidden', 'flex');
            const closeQr = () => qrModal.classList.replace('flex', 'hidden');

            document.getElementById('showQrCode')?.addEventListener('click', openQr);
            document.getElementById('closeQrCode')?.addEventListener('click', closeQr);
            qrModal?.addEventListener('click', (event) => {
                if (event.target === qrModal) closeQr();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && qrModal.classList.contains('flex')) closeQr();
            });

            // --- Live roster -----------------------------------------------
            // The server ships rendered HTML from the same Blade partial used on
            // first paint, so the polled roster can never drift from it.
            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = value;
            };

            async function pollLiveStatus() {
                try {
                    const res = await fetch(liveStatusUrl, { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;

                    const data = await res.json();

                    setText('candidateCount', data.candidate_count);
                    setText('inProgressCount', data.in_progress_count);
                    setText('completedCount', data.completed_count);
                    setText('averageScore', data.average_score ?? '—');

                    // Replacing the roster destroys and rebuilds every row, which
                    // would close an Actions menu the teacher is mid-way through
                    // using. The counters above still refresh; only the list
                    // holds until the menu is dismissed.
                    if (list?.querySelector('[data-menu-open="true"]')) {
                        return;
                    }

                    if (list && typeof data.html === 'string') {
                        list.innerHTML = data.html;
                    }
                } catch (error) {
                    // A dropped poll is not worth interrupting the teacher over;
                    // the next tick recovers on its own.
                    console.error('Live status polling failed:', error);
                }
            }

            setInterval(pollLiveStatus, 4000);
        })();
    </script>
</x-layouts.student>
