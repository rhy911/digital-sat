<x-layouts.student :user="$user" title="Classes" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    {{-- Reached only when there is no class to open yet, so this is the first
         screen a newly approved teacher sees from their approval email. It has
         to carry the create form: nothing else outside a class page does. --}}
    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user, 'classes')" />

        <main class="shell-content">
            <x-ui.flash />

            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>Classes</h2>
                        <div class="dh-desc">Organize students, assign SAT tests, and follow results.</div>
                    </div>
                </div>

                @if ($user->role === 'teacher')
                    <div class="settings-card" aria-labelledby="create-class-title">
                        <h3 class="settings-title lg" id="create-class-title">Create your first class</h3>
                        <p class="settings-help lg">
                            Start with a clear class name. You can share its join code with students once it exists,
                            then assign tests to the whole class at once.
                        </p>

                        <form method="POST" action="{{ route('teacher.classes.store') }}" class="flex flex-col gap-4 max-w-420">
                            @csrf
                            <label class="form-field-label">Class name
                                <input name="name" required maxlength="150" placeholder="SAT Prep - Summer"
                                    class="settings-input w-full mt-4" autofocus>
                            </label>
                            <label class="form-field-label">Description
                                <input name="description" maxlength="2000" placeholder="Optional context for students"
                                    class="settings-input w-full mt-4">
                            </label>
                            <button type="submit" class="btn-sm-primary btn-px-16 self-start flex-none w-auto">
                                Create class
                            </button>
                        </form>
                    </div>
                @else
                    <div class="settings-card">
                        <h3 class="settings-title lg">No classes yet</h3>
                        <p class="settings-help lg">
                            No teacher on this platform has created a class yet. Once one exists it will open here.
                        </p>
                    </div>
                @endif
            </div>
        </main>
    </div>
</x-layouts.student>
