<x-layouts.admin title="Test Dashboard">
    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        @vite(['resources/css/admin/test-builder.css'])
    @endpush

    @php
        $workspaceUrl = auth()->user()->role === 'admin'
            ? route('admin.teacher-applications.index')
            : route('home');
        $workspaceLabel = auth()->user()->role === 'admin' ? 'Admin workspace' : 'Home';
    @endphp

    <div data-test-builder-shell class="test-builder-shell flex bg-[#f6f8fb] text-[#0f172a] relative font-sans"
        x-data="{ 
            activeTab: sessionStorage.getItem('testDashboardActiveTab') ? sessionStorage.getItem('testDashboardActiveTab').replace('#', '') : 'tests', 
            mobileSidebarOpen: false,
            tabs: ['tests', 'sections', 'modules', 'questions', 'builder'],
            activateTab(tab) {
                if (tab !== 'builder' && window.confirmBuilderNavigation && !window.confirmBuilderNavigation()) {
                    return;
                }
                this.activeTab = tab;
                this.mobileSidebarOpen = false;
            },
            focusNext() {
                let cur = this.tabs.indexOf(this.activeTab);
                let next = (cur + 1) % this.tabs.length;
                let nextBtn = document.getElementById(this.tabs[next] + '-tab');
                if (nextBtn) { nextBtn.focus(); nextBtn.click(); }
            },
            focusPrev() {
                let cur = this.tabs.indexOf(this.activeTab);
                let prev = (cur - 1 + this.tabs.length) % this.tabs.length;
                let prevBtn = document.getElementById(this.tabs[prev] + '-tab');
                if (prevBtn) { prevBtn.focus(); prevBtn.click(); }
            }
        }">
        <div id="alert-container" class="fixed top-6 right-6 z-50"></div>

        <!-- Mobile Sidebar Backdrop -->
        <div x-show="mobileSidebarOpen" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="mobileSidebarOpen = false"
             class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" 
             style="display: none;"></div>

        <!-- Sidebar Navigation -->
        <aside
            id="testDashboardSidebar"
            class="test-builder-sidebar h-screen bg-[#0a1122] border-r border-[#1e293b]/70 flex flex-col shrink-0 text-slate-300 z-40 fixed inset-y-0 left-0 transform -translate-x-full transition-transform duration-200 lg:static lg:translate-x-0"
            :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            @keydown.escape.window="mobileSidebarOpen = false">
            <div class="test-builder-sidebar-header border-b border-slate-800">
                <a href="{{ $workspaceUrl }}" class="test-builder-sidebar-brand flex flex-col gap-1 no-underline" title="Return to {{ $workspaceLabel }}">
                    <span class="test-builder-sidebar-wordmark"><x-brand.wordmark size="lg" tone="inverse" /></span>
                    <span class="test-builder-sidebar-label text-[10px] text-indigo-400 font-extrabold tracking-widest uppercase">Content Suite</span>
                </a>
                <button type="button"
                    class="test-builder-sidebar-toggle"
                    data-sidebar-toggle
                    aria-controls="testDashboardSidebar"
                    aria-expanded="true"
                    aria-label="Compact sidebar"
                    title="Compact sidebar">
                    <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
                </button>
            </div>


            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-1.5" id="dashboardTabs" role="tablist"
                 @keydown.arrow-down.prevent="focusNext()"
                 @keydown.arrow-up.prevent="focusPrev()">
                <button
                    class="sidebar-link w-full text-left px-4 py-3 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'tests' }" 
                    id="tests-tab" 
                    x-on:click="activateTab('tests')"
                    data-bs-target="#tests" 
                    type="button" 
                    role="tab"
                    :aria-selected="activeTab === 'tests' ? 'true' : 'false'"
                    aria-controls="tests"
                    :tabindex="activeTab === 'tests' ? '0' : '-1'"
                    title="Practice Tests">
                    <i class="bi bi-journal-text text-lg"></i><span class="test-builder-sidebar-label">Practice Tests</span>
                </button>
                <button
                    class="sidebar-link w-full text-left px-4 py-3 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'sections' }" 
                    id="sections-tab" 
                    x-on:click="activateTab('sections')" 
                    data-bs-target="#sections" 
                    type="button" 
                    role="tab"
                    :aria-selected="activeTab === 'sections' ? 'true' : 'false'"
                    aria-controls="sections"
                    :tabindex="activeTab === 'sections' ? '0' : '-1'"
                    title="Sections">
                    <i class="bi bi-folder2-open text-lg"></i><span class="test-builder-sidebar-label">Sections</span>
                </button>
                <button
                    class="sidebar-link w-full text-left px-4 py-3 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'modules' }" 
                    id="modules-tab" 
                    x-on:click="activateTab('modules')"
                    data-bs-target="#modules" 
                    type="button" 
                    role="tab"
                    :aria-selected="activeTab === 'modules' ? 'true' : 'false'"
                    aria-controls="modules"
                    :tabindex="activeTab === 'modules' ? '0' : '-1'"
                    title="Modules">
                    <i class="bi bi-box-seam text-lg"></i><span class="test-builder-sidebar-label">Modules</span>
                </button>
                <button
                    class="sidebar-link w-full text-left px-4 py-3 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'questions' }" 
                    id="questions-tab" 
                    x-on:click="activateTab('questions')" 
                    data-bs-target="#questions" 
                    type="button" 
                    role="tab"
                    :aria-selected="activeTab === 'questions' ? 'true' : 'false'"
                    aria-controls="questions"
                    :tabindex="activeTab === 'questions' ? '0' : '-1'"
                    title="Question Bank">
                    <i class="bi bi-database text-lg"></i><span class="test-builder-sidebar-label">Question Bank</span>
                </button>
                <div class="py-2">
                    <div class="h-px bg-slate-800 w-full"></div>
                </div>
                <button
                    class="sidebar-link-builder w-full text-left px-4 py-3 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'builder' }" 
                    id="builder-tab" 
                    x-on:click="activateTab('builder')"
                    data-bs-target="#builder" 
                    type="button" 
                    role="tab"
                    :aria-selected="activeTab === 'builder' ? 'true' : 'false'"
                    aria-controls="builder"
                    :tabindex="activeTab === 'builder' ? '0' : '-1'"
                    title="Easy Builder">
                    <i class="bi bi-magic text-lg"></i><span class="test-builder-sidebar-label">Easy Builder</span>
                </button>
            </nav>

            <!-- Renamed and visually demoted Quick Author wizard button -->
            <div class="p-4 border-t border-slate-800 bg-[#0a1122]">
                <button class="w-full px-4 py-3 text-sm font-bold rounded-xl border border-slate-800 bg-[#0c1a2f] text-slate-300 hover:bg-slate-800 hover:text-white flex items-center justify-center gap-2 cursor-pointer transition-colors duration-150"
                    x-on:click="$dispatch('open-modal', 'quickAuthorWizardModal')"
                    aria-label="Quick Author Wizard"
                    title="Quick Author Wizard">
                    <i class="bi bi-magic"></i><span class="test-builder-sidebar-label">Quick Author</span>
                </button>
            </div>

            <div class="p-4 border-t border-slate-800 bg-[#070e1c]">
                <button
                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-800 bg-[#0a1524] text-slate-400 hover:text-white flex items-center justify-center gap-2.5 transition-colors duration-150 cursor-pointer"
                    onclick="refreshTestDashboardData()"
                    aria-label="Refresh data"
                    title="Refresh data">
                    <i class="bi bi-arrow-clockwise"></i><span class="test-builder-sidebar-label">Refresh Data</span>
                </button>
            </div>

            <div class="test-builder-sidebar-resizer"
                data-sidebar-resizer
                role="separator"
                aria-label="Resize sidebar"
                aria-orientation="vertical"
                aria-valuemin="208"
                aria-valuemax="384"
                aria-valuenow="256"
                tabindex="0"
                title="Drag to resize sidebar"></div>
        </aside>

        <!-- Main Content Area -->
        <main class="test-builder-main flex-1 flex flex-col bg-[#f6f8fb]">
            <!-- Modern Header inside Main Area -->
            <header class="test-builder-header flex justify-between items-center border-b border-[#e2e8f0] z-10 bg-white text-[#0f172a]">
                <div class="flex items-center min-w-0">
                    <button @click="mobileSidebarOpen = !mobileSidebarOpen" 
                            class="lg:hidden p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg mr-3 shrink-0" 
                            aria-label="Open navigation menu">
                        <i class="bi bi-list text-2xl"></i>
                    </button>
                </div>
                @auth
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="hidden sm:flex flex-col text-right">
                            <span class="text-sm font-bold text-slate-800 leading-none">{{ auth()->user()->username ?? auth()->user()->email }}</span>
                            <span class="text-[10px] text-indigo-600 font-extrabold uppercase tracking-widest mt-1.5 flex items-center gap-2 justify-end">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                                {{ auth()->user()->role === 'teacher' ? 'Teacher' : 'Administrator' }}
                            </span>
                        </div>
                        <div class="hidden sm:block w-px h-8 bg-slate-200"></div>
                        <div class="flex items-center gap-2">
                            <a href="{{ $workspaceUrl }}"
                                class="text-slate-500 hover:text-indigo-600 flex items-center justify-center w-10 h-10 rounded-xl hover:bg-slate-100"
                                title="Return to {{ $workspaceLabel }}" aria-label="Return to {{ $workspaceLabel }}">
                                <i class="bi bi-house text-xl"></i>
                            </a>
                            <form id="logoutForm" action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="text-slate-500 hover:text-rose-600 flex items-center justify-center w-10 h-10 rounded-xl hover:bg-slate-100 cursor-pointer"
                                    title="Logout" aria-label="Logout">
                                    <i class="bi bi-box-arrow-right text-xl"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </header>

            <div class="test-builder-content">
                <div class="tab-content test-builder-tab-content" id="dashboardTabContent">
                    <x-admin.test-builder.tests-tab :tests="$tests" />
                    <x-admin.test-builder.sections-tab :tests="$tests" />
                    <x-admin.test-builder.modules-tab :tests="$tests" :all-modules="$allModules" />
                    <x-admin.test-builder.questions-tab :tests="$tests" :questions="$questions"
                        :questions-total="$questionsTotal" />
                    <x-admin.test-builder.builder-tab :tests="$tests" />
                </div>
            </div>
        </main>
    </div>

    <x-admin.test-builder.modals />
    <x-admin.test-builder.quick-author-wizard />

    @push('scripts')
        <script>
            window.__currentUserRole = "{{ auth()->user()->role }}";
            window.__currentUserId = {{ auth()->id() }};
            window.TestDashboardConfig = {
                SNAPSHOT_URL: "{{ route('home-dashboard.snapshot') }}",
                QUESTIONS_LIST_URL: "{{ route('home-dashboard.questions.list') }}",
                QUESTIONS_SEARCH_URL: "{{ route('home-dashboard.questions.search') }}",
                CSV_BULK_URL: "{{ route('home-dashboard.questions.bulk-csv-store') }}",
                BULK_PREVIEW_URL: "{{ route('home-dashboard.questions.bulk-preview') }}",
                CSV_BULK_PREVIEW_URL: "{{ route('home-dashboard.questions.bulk-csv-preview') }}",
                BULK_STORE_URL: "{{ route('home-dashboard.questions.bulk-store') }}",
                MEDIA_UPLOAD_URL: "{{ route('home-dashboard.media.upload') }}",
                TESTS_STORE_URL: "{{ route('home-dashboard.tests.store') }}",
                SECTIONS_STORE_URL: "{{ route('home-dashboard.sections.store') }}",
                MODULES_STORE_URL: "{{ route('home-dashboard.modules.store') }}",
                QUESTIONS_ATTACH_URL: "{{ route('home-dashboard.questions.attach') }}",
                BASE_URL: "/admin",
                QUESTIONS_PER_PAGE: 30
            };
        </script>
        @vite(['resources/js/test-dashboard.js'])
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const logoutForm = document.getElementById('logoutForm');
                if (logoutForm && typeof window.initAjaxLogout === 'function') {
                    window.initAjaxLogout({ formEl: logoutForm, redirectTo: '/signin' });
                }
            });
        </script>
    @endpush
</x-layouts.admin>
