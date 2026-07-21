<x-layouts.student :user="$user" title="My classes" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::student('classes')" />

        <div class="ledger-pane">
            <x-ui.flash />

            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>My classes</h2>
                        <div class="dh-desc">Join a teacher's class with the eight-character code they shared.</div>
                    </div>
                </div>

                <div class="form-note" style="max-width: 420px; margin: 0 0 28px;">
                    <form method="POST" action="{{ route('student.classes.join') }}"
                        onsubmit="const btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerText = 'Requesting...';">
                        @csrf
                        <div class="field">
                            <label for="join_code">Class code</label>
                            <input id="join_code" name="join_code" value="{{ request('code') }}" minlength="8"
                                maxlength="8" required autocomplete="off" autocapitalize="characters"
                                spellcheck="false" placeholder="AB12CD34"
                                @if ($errors->any()) aria-invalid="true" @endif>
                        </div>
                        <button type="submit" class="btn-sm-primary btn-full">Request to join</button>
                    </form>
                </div>

                <div class="flex flex-col items-center text-center gap-3"
                    style="padding: 56px 24px; border: 1px dashed var(--border-strong); border-radius: 12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="var(--ink-faint)"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;">
                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" />
                        <path d="M6 6h10" />
                        <path d="M6 10h10" />
                    </svg>
                    <h3 style="margin:0; color: var(--ink);">{{ __('classroom.no_classes_heading') }}</h3>
                    <p style="margin:0; max-width:46ch; color: var(--ink-soft);">{{ __('classroom.no_classes_body') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.student>
