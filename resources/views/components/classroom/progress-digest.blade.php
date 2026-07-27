@props([
    'bestScore' => null,
    'classroomName',
    'teacherName',
])

<x-shell.corkboard header="Digest">
    <!-- Best Score Note -->
    <div class="cork-note">
        <h3>Personal Best</h3>
        <p class="digest-line" style="font-family: 'IBM Plex Mono', monospace; font-size: 1.8rem; font-weight: 900; color: var(--ds-ink-strong); margin-top: 4px;">
            {{ $bestScore ?? '—' }}
        </p>
        <p class="cork-empty mt-2">Highest estimated score achieved across all attempts.</p>
    </div>

    <!-- Classroom Note -->
    <div class="cork-note">
        <h3>Study Scope</h3>
        <p class="digest-line"><strong>{{ $classroomName }}</strong></p>
        <p class="cork-empty mt-2">Instructor: {{ $teacherName }}</p>
    </div>

    <!-- Quick Tip Note -->
    <div class="cork-note">
        <h3>Quick Tip</h3>
        <p class="cork-empty">"Double-check your Math answers using the graphic calculator. It saves time and prevents calculation slips!"</p>
    </div>
</x-shell.corkboard>
