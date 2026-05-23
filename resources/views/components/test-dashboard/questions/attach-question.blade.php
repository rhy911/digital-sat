@props(['tests'])

<!-- Attach Existing Question from Bank -->
<div class="glass-panel rounded-2xl overflow-hidden border-l-4 !border-l-indigo-500 mb-8 shadow-2xl">
    <div class="px-6 py-4 bg-slate-900/30 border-b border-slate-800/60">
        <h5 class="text-sm font-bold text-white flex items-center gap-2 mb-0">
            <i class="bi bi-link-45deg text-indigo-400 text-base leading-none animate-pulse"></i> Attach Existing Question from Bank
        </h5>
    </div>
    <form id="attachQuestionForm" class="m-0">
        @csrf
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-end">
                <div class="md:col-span-5">
                    <label for="attachToModule" class="block text-sm font-semibold text-slate-300 mb-1.5">Target Module <span class="text-rose-500">*</span></label>
                    <select class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 tom-select" id="attachToModule" name="module_id" required>
                        <option value="">Search module...</option>
                        @foreach ($tests as $test)
                            @foreach ($test->sections as $section)
                                @foreach ($section->modules as $module)
                                    <option value="{{ $module->id }}">
                                        {{ $test->title }} |
                                        {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod
                                        {{ $module->module_number }} ({{ $module->difficulty_level }})
                                    </option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-5">
                    <label for="attachQuestionId" class="block text-sm font-semibold text-slate-300 mb-1.5">Question from Bank <span class="text-rose-500">*</span></label>
                    <select class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 tom-select-remote-question" id="attachQuestionId" name="question_id" required>
                        <option value="">Search by ID or question text snippet...</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="attachPosition" class="block text-sm font-semibold text-slate-300 mb-1.5">Position</label>
                    <input type="number" class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" id="attachPosition" name="position" min="1" placeholder="Auto">
                </div>
            </div>
        </div>
        <div class="px-6 py-4 bg-slate-900/30 border-t border-slate-800/60 flex justify-end">
            <button type="submit" class="px-5 py-2 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 hover:shadow-indigo-500/20 text-white font-semibold text-sm rounded-lg shadow-sm flex items-center justify-center gap-1.5">
                <i class="bi bi-check-circle-fill text-sm leading-none"></i> Attach to Module
            </button>
        </div>
    </form>
</div>
