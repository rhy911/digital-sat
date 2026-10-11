<x-layouts.student :user="$user" :title="__('classroom.classes_title')" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    <div class="app-shell app-shell--no-list" x-data="{ catalogFilter: 'active' }">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user, 'classes')" />

        <main class="ledger-pane no-corkboard">
            <x-ui.flash />

            <div class="binder-panel">
                <div class="ledger-header flex justify-between items-start flex-wrap gap-4 mb-6">
                    <div class="dh-left">
                        <h2>{{ __('classroom.classes_title') }}</h2>
                        <div class="dh-desc">{{ __('classroom.classes_desc') }}</div>
                    </div>
                    @if ($user->role === 'teacher' || $user->role === 'admin')
                        <div class="dh-right">
                            <button type="button" class="btn-sm-primary btn-px-16"
                                @click="$dispatch('open-modal', 'modal-create-class')">
                                {{ __('classroom.create_new_class') }}
                            </button>
                        </div>
                    @endif
                </div>

                @if ($classrooms->isEmpty())
                    @if ($user->role === 'teacher' || $user->role === 'admin')
                        <div class="settings-card" aria-labelledby="create-class-title">
                            <h3 class="settings-title lg" id="create-class-title">{{ __('classroom.create_first_class') }}</h3>
                            <p class="settings-help lg">
                                {{ __('classroom.create_first_class_help') }}
                            </p>

                            <form method="POST" action="{{ route('teacher.classes.store') }}" class="flex flex-col gap-4 max-w-420">
                                @csrf
                                <div>
                                    <label for="first_class_name" class="form-field-label font-semibold">{{ __('classroom.class_name') }}</label>
                                    <input id="first_class_name" name="name" required maxlength="150" placeholder="{{ __('classroom.class_name_placeholder') }}"
                                        class="settings-input w-full mt-1" autofocus>
                                </div>
                                <div>
                                    <label for="first_class_desc" class="form-field-label font-semibold">{{ __('classroom.description') }}</label>
                                    <input id="first_class_desc" name="description" maxlength="2000" placeholder="{{ __('classroom.class_desc_placeholder') }}"
                                        class="settings-input w-full mt-1">
                                </div>
                                <button type="submit" class="btn-sm-primary btn-px-16 self-start flex-none w-auto mt-2">
                                    {{ __('classroom.create_class') }}
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="settings-card">
                            <h3 class="settings-title lg">{{ __('classroom.no_classes_teacher_yet') }}</h3>
                            <p class="settings-help lg">
                                {{ __('classroom.no_classes_teacher_body') }}
                            </p>
                        </div>
                    @endif
                @else
                    <!-- CATALOG FILTER TABS -->
                    <div class="catalog-filter-bar mb-6 flex items-center justify-between flex-wrap gap-3">
                        <div class="flex gap-2">
                            <button type="button" class="catalog-tab-btn"
                                :class="{ 'active': catalogFilter === 'active' }"
                                @click="catalogFilter = 'active'">
                                Active ({{ $activeClassrooms->count() }})
                            </button>
                            <button type="button" class="catalog-tab-btn"
                                :class="{ 'active': catalogFilter === 'archived' }"
                                @click="catalogFilter = 'archived'">
                                Archived ({{ $archivedClassrooms->count() }})
                            </button>
                            <button type="button" class="catalog-tab-btn"
                                :class="{ 'active': catalogFilter === 'all' }"
                                @click="catalogFilter = 'all'">
                                All ({{ $classrooms->count() }})
                            </button>
                        </div>
                    </div>

                    <!-- CARD GRID -->
                    <div class="class-catalog-grid">
                        @foreach ($classrooms as $c)
                            <div class="class-catalog-card"
                                x-show="catalogFilter === 'all' || catalogFilter === '{{ $c->status }}'"
                                x-transition>
                                <div class="card-top">
                                    <div class="card-title-row">
                                        <a href="{{ route('teacher.classes.show', $c) }}" class="card-title">
                                            {{ $c->name }}
                                        </a>
                                        <span class="status-pill {{ $c->status === 'active' ? 'ok' : 'draft' }}">
                                            <span class="d"></span>{{ ucfirst($c->status) }}
                                        </span>
                                    </div>
                                    <p class="card-desc">
                                        {{ $c->description ?: __('classroom.manage_class_desc') }}
                                    </p>
                                </div>

                                <div class="card-stats-row">
                                    <div class="stat-pill">
                                        <strong>{{ $c->active_memberships_count }}</strong> students
                                    </div>
                                    <div class="stat-pill">
                                        <strong>{{ $c->assignments_count }}</strong> assignments
                                    </div>
                                    <div class="stat-pill">
                                        <strong>{{ $c->documents_count }}</strong> materials
                                    </div>
                                    @if ($c->pending_memberships_count > 0)
                                        <div class="stat-pill alert-pill">
                                            <strong>{{ $c->pending_memberships_count }}</strong> pending
                                        </div>
                                    @endif
                                </div>

                                <div class="card-footer">
                                    <div class="join-code-chip" x-data="{ copied: false }">
                                        <span class="label">Code:</span>
                                        <button type="button" class="code-val"
                                            @click="copyJoinCode('{{ $c->join_code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            title="{{ __('classroom.copy_join_code') }}">
                                            <span>{{ $c->join_code }}</span>
                                            <span x-show="copied" x-cloak class="copied-notice">{{ __('classroom.join_code_copied') }}</span>
                                        </button>
                                    </div>
                                    <a href="{{ route('teacher.classes.show', $c) }}" class="btn-sm-primary btn-px-16">
                                        Open Class →
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </main>

        <!-- CREATE CLASS MODAL -->
        @if ($user->role === 'teacher' || $user->role === 'admin')
            <x-ui.modal id="modal-create-class" title="{{ __('classroom.create_new_class') }}" maxWidth="md">
                <form method="POST" action="{{ route('teacher.classes.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <div>
                        <label for="modal_class_name" class="block font-semibold text-xs uppercase tracking-wider text-slate-600 mb-1.5">{{ __('classroom.class_name') }}</label>
                        <input id="modal_class_name" name="name" required maxlength="150"
                            placeholder="{{ __('classroom.class_name_placeholder') }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition" autofocus>
                    </div>
                    <div>
                        <label for="modal_class_desc" class="block font-semibold text-xs uppercase tracking-wider text-slate-600 mb-1.5">{{ __('classroom.description') }}</label>
                        <textarea id="modal_class_desc" name="description" maxlength="2000" rows="3"
                            placeholder="{{ __('classroom.class_desc_placeholder') }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition resize-v"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-200/80">
                        <button type="button" class="btn-sm-ghost"
                            x-on:click="$dispatch('close-modal', 'modal-create-class')">
                            {{ __('classroom.cancel') }}
                        </button>
                        <button type="submit" class="btn-sm-primary btn-px-16 shadow-sm">
                            {{ __('classroom.create_class') }}
                        </button>
                    </div>
                </form>
            </x-ui.modal>
        @endif
    </div>

    @push('scripts')
        <script>
            function copyJoinCode(code) {
                if (window.copyToClipboard) {
                    return window.copyToClipboard(code);
                }
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(code).catch(() => fallbackCopy(code));
                }
                return fallbackCopy(code);

                function fallbackCopy(text) {
                    const scratch = document.createElement('textarea');
                    scratch.value = text;
                    scratch.setAttribute('readonly', '');
                    scratch.style.position = 'fixed';
                    scratch.style.top = '-9999px';
                    scratch.style.left = '-9999px';
                    document.body.appendChild(scratch);
                    scratch.select();
                    try {
                        document.execCommand('copy');
                    } catch (e) {
                        console.error('Fallback copy failed', e);
                    }
                    scratch.remove();
                }
            }
        </script>
    @endpush
</x-layouts.student>
