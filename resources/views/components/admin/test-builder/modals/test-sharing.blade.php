<x-ui.modal id="testSharingModal" max-width="2xl">
    <x-slot:title>
        <div>
            <h4 class="text-base font-extrabold leading-tight text-slate-900">Manage sharing</h4>
            <p id="testSharingSubtitle" class="mt-1 text-xs font-medium text-slate-600">Give approved teachers access to view, clone, and assign this test.</p>
        </div>
    </x-slot:title>

    <div class="test-sharing-modal space-y-5" data-test-sharing-modal>
        <input type="hidden" id="shareTestId">
        <div id="shareAlertWrapper" class="grid grid-rows-[0fr] transition-[grid-template-rows] duration-300 ease-[var(--ease-out-quart,cubic-bezier(0.25,1,0.5,1))]">
            <div class="overflow-hidden">
                <div id="shareAlertContainer" class="rounded-lg p-3 text-sm font-medium mb-3 opacity-0 transition-opacity duration-300"></div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <label for="shareTeacherSearch" class="block text-sm font-bold text-slate-800">Approved teacher</label>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <div class="relative flex-1">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <input id="shareTeacherSearch" type="search" autocomplete="off"
                        class="w-full rounded-lg border border-slate-300 bg-white py-3 pl-9 pr-3 text-sm font-medium text-slate-900 placeholder-slate-500 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/15"
                        placeholder="Search name or email">
                    <div id="shareTeacherResults" class="test-sharing-results hidden" role="listbox"></div>
                </div>
                <button type="button" id="shareTeacherAdd"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    disabled>
                    <i class="bi bi-person-plus" aria-hidden="true"></i>Add
                </button>
            </div>
            <p id="shareTeacherHint" class="mt-2 text-xs font-medium text-slate-600">Shared teachers cannot edit the original or change status.</p>
        </div>

        <section>
            <div class="mb-3 flex items-center justify-between gap-3">
                <h5 class="text-sm font-extrabold text-slate-900">People with access</h5>
                <span id="shareCountBadge" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">0 teachers</span>
            </div>
            <div id="shareList" class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white"></div>
            <div id="shareEmptyState" class="hidden rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
                <h6 class="text-sm font-extrabold text-slate-900">No teachers shared yet</h6>
                <p class="mt-1 text-sm text-slate-600">Add a teacher by name or email when they need to assign this test.</p>
            </div>
        </section>
    </div>
</x-ui.modal>
