<x-layouts.admin title="Test Dashboard">
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,100..900;1,100..900&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        @vite(['resources/css/test-dashboard-admin.css'])
    @endpush

    <div class="fixed inset-0 z-40 flex h-screen w-screen overflow-hidden bg-[#0b0f19] dark-theme-dashboard"
        x-data="{ activeTab: sessionStorage.getItem('testDashboardActiveTab') ? sessionStorage.getItem('testDashboardActiveTab').replace('#', '') : 'tests' }">
        <div id="alert-container" class="fixed top-6 right-6 z-50"></div>

        <!-- Sidebar Navigation -->
        <aside
            class="w-72 bg-slate-950/80 border-r border-slate-800/80 flex flex-col shrink-0 text-slate-300 relative z-20">
            <div class="p-8 border-b border-slate-850 flex items-center gap-3">
                <div class="sidebar-logo-box w-10 h-10 rounded-xl flex items-center justify-center text-white">
                    <i class="bi bi-mortarboard-fill text-xl"></i>
                </div>
                <div>
                    <h1 class="text-lg font-extrabold text-white tracking-tight leading-none">Digital SAT</h1>
                    <span
                        class="text-[10px] text-indigo-400 font-extrabold tracking-widest uppercase mt-1 block">Content
                        Suite</span>
                </div>
            </div>

            <div class="p-6">
                <button class="btn-new-content w-full px-5 py-4 rounded-xl flex items-center justify-center gap-3"
                    x-on:click="$dispatch('open-modal', 'quickAuthorWizardModal')">
                    <i class="bi bi-magic text-base animate-pulse"></i> New Content
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 space-y-1.5" id="dashboardTabs" role="tablist">
                <button
                    class="sidebar-link w-full text-left px-4 py-3.5 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'tests' }" id="tests-tab" x-on:click="activeTab = 'tests'"
                    data-bs-target="#tests" type="button" role="tab">
                    <i class="bi bi-journal-text text-lg"></i> Practice Tests
                </button>
                <button
                    class="sidebar-link w-full text-left px-4 py-3.5 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'sections' }" id="sections-tab"
                    x-on:click="activeTab = 'sections'" data-bs-target="#sections" type="button" role="tab">
                    <i class="bi bi-folder2-open text-lg"></i> Sections
                </button>
                <button
                    class="sidebar-link w-full text-left px-4 py-3.5 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'modules' }" id="modules-tab" x-on:click="activeTab = 'modules'"
                    data-bs-target="#modules" type="button" role="tab">
                    <i class="bi bi-box-seam text-lg"></i> Modules
                </button>
                <button
                    class="sidebar-link w-full text-left px-4 py-3.5 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'questions' }" id="questions-tab"
                    x-on:click="activeTab = 'questions'" data-bs-target="#questions" type="button" role="tab">
                    <i class="bi bi-database text-lg"></i> Question Bank
                </button>
                <div class="py-2">
                    <div class="h-px bg-slate-900 w-full"></div>
                </div>
                <button
                    class="sidebar-link-builder w-full text-left px-4 py-3.5 rounded-xl text-sm flex items-center gap-3 cursor-pointer"
                    :class="{ 'active': activeTab === 'builder' }" id="builder-tab" x-on:click="activeTab = 'builder'"
                    data-bs-target="#builder" type="button" role="tab">
                    <i class="bi bi-magic text-lg"></i> Easy Builder
                </button>
            </nav>

            <div class="p-6 border-t border-slate-900 bg-slate-950/40">
                <button
                    class="btn-refresh-data w-full px-4 py-2.5 text-xs rounded-xl flex items-center justify-center gap-2.5"
                    onclick="refreshTestDashboardData(captureTomSelectPreservation(null))">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Data
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#0b0f19]">
            <!-- Modern Header inside Main Area -->
            <header
                class="flex justify-between items-center border-b border-slate-800/80 shadow-md z-10 px-8! py-3! bg-slate-950/40 text-slate-100">
                <div class="text-left">
                    <h2 class="dashboard-title-gradient text-xl font-extrabold m-0" id="dashboard-active-title">Test
                        Dashboard</h2>
                </div>
                @auth
                    <div class="flex items-center gap-4">
                        <div class="flex flex-col text-right">
                            <span
                                class="text-sm font-bold text-slate-200 leading-none">{{ auth()->user()->username ?? auth()->user()->email }}</span>
                            <span
                                class="text-[10px] text-indigo-400 font-extrabold uppercase tracking-widest mt-1.5 flex items-center gap-1"><span
                                    class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-ping"></span> Administrator</span>
                        </div>
                        <div class="w-px h-8 bg-slate-800"></div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('home') }}"
                                class="text-slate-400 hover:text-indigo-400 flex items-center justify-center w-10 h-10 rounded-xl hover:bg-slate-900"
                                title="Go to home">
                                <i class="bi bi-house text-xl"></i>
                            </a>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="text-slate-400 hover:text-rose-400 flex items-center justify-center w-10 h-10 rounded-xl hover:bg-slate-900 cursor-pointer"
                                    title="Logout">
                                    <i class="bi bi-box-arrow-right text-xl"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </header>

            <div class="flex-1 overflow-y-auto p-8 md:p-4">
                <div class="tab-content h-full" id="dashboardTabContent">
                    <x-test-dashboard.tests-tab :tests="$tests" />
                    <x-test-dashboard.sections-tab :tests="$tests" />
                    <x-test-dashboard.modules-tab :tests="$tests" :all-modules="$allModules" />
                    <x-test-dashboard.questions-tab :tests="$tests" :questions="$questions"
                        :questions-total="$questionsTotal" />
                    <x-test-dashboard.builder-tab :tests="$tests" />
                </div>
            </div>
        </main>
    </div>

    <x-test-dashboard.modals />
    <x-test-dashboard.quick-author-wizard />

    @push('scripts')
        <script>
            window.TestDashboardConfig = {
                SNAPSHOT_URL: "{{ route('test-dashboard.snapshot') }}",
                QUESTIONS_LIST_URL: "{{ route('test-dashboard.questions.list') }}",
                QUESTIONS_SEARCH_URL: "{{ route('test-dashboard.questions.search') }}",
                CSV_BULK_URL: "{{ route('test-dashboard.questions.bulk-csv-store') }}",
                BULK_PREVIEW_URL: "{{ route('test-dashboard.questions.bulk-preview') }}",
                CSV_BULK_PREVIEW_URL: "{{ route('test-dashboard.questions.bulk-csv-preview') }}",
                BULK_STORE_URL: "{{ route('test-dashboard.questions.bulk-store') }}",
                MEDIA_UPLOAD_URL: "{{ route('test-dashboard.media.upload') }}",
                TESTS_STORE_URL: "{{ route('test-dashboard.tests.store') }}",
                SECTIONS_STORE_URL: "{{ route('test-dashboard.sections.store') }}",
                SECTIONS_LINK_MODULE_URL: "{{ route('test-dashboard.sections.link-module') }}",
                MODULES_STORE_URL: "{{ route('test-dashboard.modules.store') }}",
                QUESTIONS_ATTACH_URL: "{{ route('test-dashboard.questions.attach') }}",
                BASE_URL: "/test-dashboard",
                QUESTIONS_PER_PAGE: 30
            };
        </script>
        @vite(['resources/js/test-dashboard.js'])
    @endpush
</x-layouts.admin>