@props(['tests'])

<div x-show="activeTab === 'builder'" id="builder" role="tabpanel" style="display: none;" x-transition.opacity.duration.300ms>
    <!-- Easy Question Builder Card -->
    <div class="rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden border-l-4 border-amber-500 mb-6 glass-panel">
        <div class="px-6 py-4 bg-slate-950/40 border-b border-slate-800/80 flex justify-between items-center">
            <h5 class="font-extrabold text-white flex items-center gap-3 mb-0 text-base">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-center">
                    <i class="bi bi-magic text-amber-400"></i>
                </div>
                Easy Question Builder
            </h5>
            <span class="bg-amber-500/10 border border-amber-500/20 text-amber-400 font-extrabold px-3 py-1 text-xs rounded-full uppercase tracking-wider">Step-by-Step Mode</span>
        </div>
        
        <div class="p-6">
            <div class="bg-indigo-500/5 border border-indigo-500/15 rounded-xl p-4 flex gap-4 items-start shadow-xl mb-6">
                <div class="w-10 h-10 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center shrink-0">
                    <i class="bi bi-info-circle-fill text-indigo-400 text-lg"></i>
                </div>
                <div>
                    <h6 class="text-xs font-extrabold text-indigo-400 uppercase tracking-wider mb-1">Quick Instruction</h6>
                    <p class="text-xs text-indigo-300 leading-relaxed mb-0 font-medium">
                        Select a module first, then add as many questions as you want. Each question is a "Block". We will automatically format your text.
                    </p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 mb-6">
                <div class="md:col-span-8">
                    <label for="builderModuleId" class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">1. Select Target Module <span class="text-rose-500">*</span></label>
                    <select class="form-select tom-select" id="builderModuleId" required>
                        <option value="">Search module...</option>
                        @foreach($tests as $test)
                            @foreach($test->sections as $section)
                                @foreach($section->modules as $module)
                                <option value="{{ $module->id }}" data-section-type="{{ $section->type }}">
                                    {{ $test->title }} | {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod {{ $module->module_number }} ({{ $module->difficulty_level }})
                                </option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label for="builderStartPosition" class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">2. Start Position</label>
                    <input type="number" class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="builderStartPosition" value="1" min="1">
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left Sidebar Navigator -->
                <div class="lg:col-span-3">
                    <div class="sticky top-6">
                        <div class="rounded-2xl border border-slate-800/60 bg-slate-900/40 shadow-xl overflow-hidden">
                            <div class="px-4 py-3 bg-slate-950/40 border-b border-slate-800/80">
                                <h6 class="text-xs font-extrabold text-slate-350 flex items-center gap-2 mb-0 uppercase tracking-wider">
                                    <i class="bi bi-compass text-amber-450"></i> Workspace Index
                                </h6>
                            </div>
                            <div class="p-3">
                                <div class="flex flex-col gap-1.5 max-h-[400px] overflow-y-auto" id="builderSidebarNavigator">
                                    <div class="text-slate-500 text-center py-8 text-xs font-medium">
                                        <i class="bi bi-layers text-2xl block mb-2 text-slate-650"></i>
                                        Add a question to start indexing
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Middle Question Builder Workspace -->
                <div class="lg:col-span-5 relative builder-workspace-scroller" id="builderWorkspaceScroller">
                    <!-- Sticky Breadcrumb -->
                    <div class="sticky top-0 bg-[#0b0f19]/80 border-b border-slate-850 pb-3 mb-4 pt-1 z-3 hidden" id="builderInteractiveBreadcrumb">
                        <nav aria-label="breadcrumb">
                          <ol class="flex items-center gap-1.5 text-xs text-slate-400 mb-0">
                            <li class="flex items-center gap-1.5">
                                <i class="bi bi-journal-text text-sm text-slate-450"></i>
                                <span id="bc-test-title" class="truncate font-semibold text-slate-300 max-w-[100px]" title="">Test</span>
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-slate-600">/</span>
                                <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                                    <span class="cursor-pointer font-semibold text-slate-300 hover:text-indigo-400" @click="open = !open" id="bc-section-title">Section</span>
                                    <ul x-show="open" @click="open = false" x-transition style="display: none;" class="absolute left-0 z-50 mt-2 min-w-[12rem] py-1 shadow-md border border-slate-800 rounded-xl text-xs bg-[#131b2e]" id="bc-section-dropdown"></ul>
                                </div>
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-slate-600">/</span>
                                <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                                    <span class="cursor-pointer font-bold text-indigo-400 hover:text-indigo-300" @click="open = !open" id="bc-module-title">Module</span>
                                    <ul x-show="open" @click="open = false" x-transition style="display: none;" class="absolute left-0 z-50 mt-2 min-w-[12rem] py-1 shadow-md border border-slate-800 rounded-xl text-xs bg-[#131b2e]" id="bc-module-dropdown"></ul>
                                </div>
                            </li>
                          </ol>
                        </nav>
                    </div>

                    <div id="builderBlocksContainer" class="space-y-4">
                        <!-- Question blocks will be added here -->
                    </div>
                    
                    <div class="mt-4">
                        <button type="button" class="w-full py-3 border border-dashed border-amber-500/40 hover:border-amber-500 hover:bg-amber-500/5 text-amber-400 hover:text-amber-300 font-extrabold text-xs uppercase tracking-wider rounded-xl flex items-center justify-center gap-2 cursor-pointer" id="addBuilderBlockBtn">
                            <i class="bi bi-plus-circle-fill"></i> Add Another Question
                        </button>
                    </div>
                </div>

                <!-- Right Live Preview Drawer -->
                <div class="lg:col-span-4">
                    <div class="sticky top-6">
                        <div class="rounded-2xl border border-slate-800/60 bg-slate-900/40 shadow-xl overflow-hidden flex flex-col live-preview-drawer-container">
                            <div class="px-4 py-3 bg-slate-950 border-b border-slate-800/80">
                                <h6 class="text-xs font-extrabold text-slate-350 flex items-center gap-2 mb-0 uppercase tracking-wider">
                                    <i class="bi bi-eye-fill text-amber-450"></i> Bluebook Live Preview
                                </h6>
                            </div>
                            <div class="p-4 overflow-y-auto flex-grow bg-slate-950/40" id="builderLivePreviewDrawer">
                                <div class="text-slate-500 text-center py-12 text-xs font-medium">
                                    <i class="bi bi-file-earmark-richtext text-3xl block mb-2 text-slate-650"></i>
                                    Live compilation of STEM and formulas will appear here in real-time
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 bg-slate-950/40 border-t border-slate-800/80 flex justify-between items-center gap-4">
            <span id="builderAutoSaveIndicator" class="text-slate-450 text-xs font-medium -opacity opacity-0 flex items-center gap-2">
                <i class="bi bi-cloud-check text-emerald-450 text-base"></i> Draft saved at <span class="time font-extrabold text-white"></span>
            </span>
            <div class="flex gap-2">
                <button type="button" class="px-5 py-3 bg-slate-900/60 border border-slate-800/80 text-slate-200 font-extrabold text-xs uppercase tracking-wider rounded-xl hover:bg-slate-850 hover:text-white shadow-lg flex items-center justify-center gap-2 cursor-pointer" id="clearBuilderBtn">
                    <i class="bi bi-trash"></i> Clear All
                </button>
                <button type="button" class="px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-450 hover:to-orange-450 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-amber-500/20 transform flex items-center justify-center gap-2 cursor-pointer" id="submitBuilderBtn" title="Shortcut: Ctrl+S">
                    <i class="bi bi-cloud-arrow-up"></i> Save All Questions
                </button>
            </div>
        </div>
    </div>
</div>
