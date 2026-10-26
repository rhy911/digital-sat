<x-layouts.student :user="$user" :title="__('classroom.my_classes')" header-type="none" ui="product">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    <div class="app-shell app-shell--no-list" x-data="{}">
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::student('classes')" />

        <main class="ledger-pane no-corkboard">
            <x-ui.flash />

            <div class="binder-panel">
                <div class="ledger-header flex justify-between items-start flex-wrap gap-4 mb-6">
                    <div class="dh-left">
                        <h2>{{ __('classroom.my_classes') }}</h2>
                        <div class="dh-desc">{{ __('classroom.student_default_desc') }}</div>
                    </div>
                    <div class="dh-right">
                        <button type="button" class="btn-sm-primary btn-px-16"
                            @click="$dispatch('open-modal', 'modal-join-class')">
                            {{ __('classroom.join_class_cta') }}
                        </button>
                    </div>
                </div>

                @if ($pendingMemberships->isNotEmpty())
                    <div class="p-4 mb-6 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <x-ui.icon name="clock" class="w-4 h-4 text-amber-700 flex-shrink-0" />
                            <span>You have <strong>{{ $pendingMemberships->count() }}</strong> pending class request(s) awaiting teacher approval.</span>
                        </div>
                    </div>
                @endif

                @if ($activeMemberships->isEmpty())
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
                        <button type="button" class="btn-sm-primary mt-2"
                            @click="$dispatch('open-modal', 'modal-join-class')">
                            {{ __('classroom.join_class_cta') }}
                        </button>
                    </div>
                @else
                    <div class="class-catalog-grid">
                        @foreach ($activeMemberships as $membership)
                            @php $c = $membership->classroom; @endphp
                            <div class="class-catalog-card">
                                <div class="card-top">
                                    <div class="card-title-row">
                                        <a href="{{ route('student.classes.show', $c) }}" class="card-title">
                                            {{ $c->name }}
                                        </a>
                                        <span class="status-pill {{ $c->status === 'active' ? 'ok' : 'draft' }}">
                                            <span class="d"></span>{{ ucfirst($c->status) }}
                                        </span>
                                    </div>
                                    <p class="card-desc">
                                        {{ $c->description ?: __('classroom.student_default_desc') }}
                                    </p>
                                    @if ($c->owner)
                                        <div class="text-xs text-slate-500 mt-2">
                                            Teacher: <strong class="text-slate-700">{{ $c->owner->name }}</strong>
                                        </div>
                                    @endif
                                </div>

                                <div class="card-stats-row">
                                    <div class="stat-pill">
                                        <strong>{{ $c->assignments_count }}</strong> assignments
                                    </div>
                                    <div class="stat-pill">
                                        <strong>{{ $c->active_memberships_count }}</strong> classmates
                                    </div>
                                </div>

                                <div class="card-footer justify-end">
                                    <a href="{{ route('student.classes.show', $c) }}" class="btn-sm-primary btn-px-16 w-full text-center">
                                        Enter Classroom →
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </main>

        <!-- JOIN CLASS MODAL -->
        <x-ui.modal id="modal-join-class" title="{{ __('classroom.join_modal_title') }}" maxWidth="sm">
            <form method="POST" action="{{ route('student.classes.join') }}" class="flex flex-col gap-4">
                @csrf
                <div>
                    <label for="modal_join_code" class="form-field-label font-semibold">{{ __('classroom.modal_join_code_label') }}</label>
                    <input id="modal_join_code" name="join_code" value="{{ request('code') }}" minlength="8" maxlength="8" required
                        placeholder="AB12CD34" class="settings-input w-full mt-1 uppercase font-mono tracking-widest text-center text-lg"
                        autocomplete="off" autocapitalize="characters" spellcheck="false" autofocus>
                    <span class="text-xs text-slate-500 mt-1 block">Enter the 8-character code given by your teacher.</span>
                </div>
                <div class="flex justify-end gap-2 mt-2">
                    <button type="button" class="btn-sm-ghost"
                        x-on:click="$dispatch('close-modal', 'modal-join-class')">
                        {{ __('classroom.cancel') }}
                    </button>
                    <button type="submit" class="btn-sm-primary btn-px-16">
                        {{ __('classroom.request_to_join') }}
                    </button>
                </div>
            </form>
        </x-ui.modal>
    </div>
</x-layouts.student>
