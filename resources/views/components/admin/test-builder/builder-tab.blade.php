@props(['tests'])

<div x-show="activeTab === 'builder'" id="builder" role="tabpanel" aria-labelledby="builder-tab"
    :aria-hidden="activeTab === 'builder' ? 'false' : 'true'" :class="{ 'active': activeTab === 'builder' }"
    class="tab-pane" style="display: none;">
    <!-- Easy Question Builder Card -->
    <div class="dash-panel mb-5 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
            <h5 class="font-bold text-slate-800 mb-0 text-sm">Question workspace</h5>
            <div class="flex items-center gap-2">
                <button type="button" id="builderPreviewToggle" aria-expanded="false"
                    class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 xl:hidden">
                    <i class="bi bi-eye" aria-hidden="true"></i><span>Show preview</span>
                </button>
                <span id="builderActiveCountBadge" class="bg-indigo-50 border border-indigo-100 text-indigo-700 font-bold px-3 py-1 text-xs rounded-full">0 questions</span>
            </div>
        </div>

        <div class="p-5">
            <div x-data="{ dismissed: localStorage.getItem('test_builder_instructions_dismissed') === 'true' }" x-show="!dismissed" class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 flex gap-4 items-start mb-5 relative group">
                <div class="w-10 h-10 rounded-lg bg-white border border-indigo-100 flex items-center justify-center shrink-0">
                    <i class="bi bi-info-circle-fill text-indigo-600 text-lg"></i>
                </div>
                <div class="pr-12">
                    <h6 class="text-xs font-bold text-indigo-700 mb-1">Start here</h6>
                    <p class="text-xs text-indigo-800 leading-relaxed mb-0 font-medium">
                        Choose a module, set the first question number, then add your first question card. The preview updates markdown and LaTeX as you write.
                    </p>
                </div>
                <button type="button" id="builderDismissInstructionsBtn" @click="localStorage.setItem('test_builder_instructions_dismissed', 'true'); dismissed = true" class="absolute top-4 right-4 text-indigo-600 hover:text-indigo-700 text-xs font-bold flex items-center gap-1 cursor-pointer transition-colors duration-150 py-1 px-2.5 rounded-lg bg-white hover:bg-indigo-50 border border-indigo-100" aria-label="Dismiss Instructions">
                    <i class="bi bi-x-lg text-[10px]"></i> Got it
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 mb-5">
                <div class="md:col-span-8">
                    <label for="builderModuleId" class="text-xs font-bold text-slate-600 mb-2 block">Module <span class="text-rose-500">*</span></label>
                    <select class="form-select tom-select w-full bg-white border border-slate-200 text-slate-800 text-sm placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 rounded-xl" id="builderModuleId" required>
                        <option value="">Search module...</option>
                        @php
                            $hasModules = false;
                            foreach($tests as $test) {
                                foreach($test->sections as $section) {
                                    foreach($section->modules as $module) {
                                        if (auth()->user()->role !== 'teacher' || $module->created_by === auth()->id()) {
                                            $hasModules = true;
                                            break 3;
                                        }
                                    }
                                }
                            }
                        @endphp
                        @if (!$hasModules)
                            <option value="" disabled>No data yet</option>
                        @endif
                        @foreach($tests as $test)
                            @foreach($test->sections as $section)
                                @foreach($section->modules as $module)
                                    @if(auth()->user()->role !== 'teacher' || $module->created_by === auth()->id())
                                        <option value="{{ $module->id }}" data-section-type="{{ $section->type }}">
                                            {{ $test->title }} | {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod {{ $module->module_number }} ({{ $module->difficulty_level }})
                                        </option>
                                    @endif
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label for="builderStartPosition" class="text-xs font-bold text-slate-600 mb-2 block">Start at question</label>
                    <input type="number" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200" id="builderStartPosition" value="1" min="1">
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 builder-grid-empty" id="builderMainGrid">
                <!-- Left Sidebar Navigator -->
                <div class="lg:col-span-3 left-navigator">
                    <div class="sticky top-6">
                        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                                <h6 class="text-xs font-bold text-slate-600 flex items-center gap-2 mb-0">
                                    <i class="bi bi-compass text-indigo-650"></i> Module questions
                                </h6>
                            </div>
                            <div class="p-3">
                                <div class="flex flex-col gap-1.5 max-h-[400px] overflow-y-auto" id="builderSidebarNavigator">
                                    <div class="text-slate-400 text-center py-8 text-xs font-medium">
                                        <i class="bi bi-layers text-2xl block mb-2 text-slate-350"></i>
                                        Add a question card to start
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Middle Question Builder Workspace -->
                <div class="lg:col-span-5 relative builder-workspace-scroller workspace-panel" id="builderWorkspaceScroller">
                    <!-- Sticky Breadcrumb -->
                    <div class="sticky top-0 bg-white/95 backdrop-blur-xs border-b border-slate-200 pb-3 mb-4 pt-1 z-3 hidden" id="builderInteractiveBreadcrumb">
                        <nav aria-label="breadcrumb">
                            <ol class="flex items-center gap-1.5 text-xs text-slate-500 mb-0">
                                <li class="flex items-center gap-1.5">
                                    <i class="bi bi-journal-text text-sm text-slate-400"></i>
                                    <span id="bc-test-title" class="truncate font-semibold text-slate-700 max-w-[100px]" title="Test">Test</span>
                                </li>
                                <li class="flex items-center gap-1.5">
                                    <span class="text-slate-450">/</span>
                                    <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                                        <span class="cursor-pointer font-semibold text-slate-700 hover:text-indigo-600" @click="open = !open" id="bc-section-title">Section</span>
                                        <ul x-show="open" @click="open = false" x-transition style="display: none;" class="absolute left-0 z-50 mt-2 min-w-48 py-1 shadow-md border border-slate-250 rounded-xl text-xs bg-white text-slate-700" id="bc-section-dropdown"></ul>
                                    </div>
                                </li>
                                <li class="flex items-center gap-1.5">
                                    <span class="text-slate-450">/</span>
                                    <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                                        <span class="cursor-pointer font-bold text-indigo-650 hover:text-indigo-700" @click="open = !open" id="bc-module-title">Module</span>
                                        <ul x-show="open" @click="open = false" x-transition style="display: none;" class="absolute left-0 z-50 mt-2 min-w-48 py-1 shadow-md border border-slate-250 rounded-xl text-xs bg-white text-slate-700" id="bc-module-dropdown"></ul>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                    </div>

                    <div id="builderBlocksContainer" class="space-y-4">
                        <!-- Question blocks will be added here -->
                    </div>

                    <div>
                        <button type="button" class="w-full py-3 border border-dashed border-indigo-200 hover:border-indigo-500 hover:bg-indigo-50 text-indigo-600 font-semibold text-sm rounded-xl flex items-center justify-center gap-2 cursor-pointer transition-all duration-150" id="addBuilderBlockBtn">
                            <i class="bi bi-plus-circle-fill"></i> Add question card
                        </button>
                    </div>
                </div>

                <!-- Right Live Preview Drawer -->
                <div class="lg:col-span-4 live-preview-drawer">
                    <div class="sticky top-6">
                        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden flex flex-col live-preview-drawer-container">
                            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                                <h6 class="text-xs font-bold text-slate-600 flex items-center gap-2 mb-0">
                                    <i class="bi bi-eye-fill text-indigo-600"></i> Live preview
                                </h6>
                            </div>
                            <div class="p-4 overflow-y-auto grow bg-white" id="builderLivePreviewDrawer">
                                <div class="text-slate-400 text-center py-12 text-xs font-medium">
                                    <i class="bi bi-file-earmark-richtext text-3xl block mb-2 text-slate-350"></i>
                                    A Bluebook-style preview appears as you write.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="builder-save-footer px-6 py-4 bg-white border-t border-slate-200 flex justify-between items-center gap-4">
            <span id="builderAutoSaveIndicator" class="text-slate-500 text-xs font-medium opacity-0 flex items-center gap-2" aria-live="polite">
                <i class="bi bi-cloud-check text-emerald-600 text-base"></i> Draft saved at <span class="time font-bold text-slate-800"></span>
            </span>
            <div class="flex gap-2">
                <button type="button" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 font-semibold text-sm rounded-lg hover:bg-slate-50 shadow-sm flex items-center justify-center gap-2 cursor-pointer transition-colors duration-150" id="clearBuilderBtn">
                    <i class="bi bi-trash"></i> Clear all
                </button>
                <button type="button" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 font-semibold text-sm rounded-lg hover:bg-slate-50 shadow-sm flex items-center justify-center gap-2 cursor-pointer transition-colors duration-150" id="clearUnchangedBtn" title="Clear opened questions with no modifications">
                    <i class="bi bi-eraser"></i> Clear unchanged
                </button>
                <button type="button" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg shadow-sm flex items-center justify-center gap-2 cursor-pointer transition-colors duration-150" id="submitBuilderBtn" title="Shortcut: Ctrl+S">
                    <i class="bi bi-cloud-arrow-up"></i> Save questions
                </button>
            </div>
        </div>
    </div>
</div>
