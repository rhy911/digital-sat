<x-layouts.app-progress :user="$user" title="My Practice - Digital SAT">
    @push('styles')
        @vite(['resources/css/home-progress.css', 'resources/css/practice.css'])
    @endpush
    <div class="welcome bg-[#0077c8] mb-6">
        <div class="md:container md:mx-auto px-4 pb-6">
            <h1 class="text-3xl md:text-4xl font-normal text-white mb-2">My Practice</h1>
            <p class="text-lg text-white">Review your practice test scores, dig deeper into your performance, and learn your strengths before test day.</p>
        </div>
    </div>
    
    <div class="md:container md:mx-auto px-4 pb-20">
        <a href="{{ route('home.progress') }}" class="inline-flex w-fit items-center text-[#324dc7] text-decoration-none me-3 gap-1 mb-10">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span class="font-bold">Back to Home</span>
        </a>

        <h1 class="text-3xl md:text-4xl font-bold text-[#000] mb-4">SAT Practice Tests</h1>
        <div class="grid gap-6" style="grid-template-columns: repeat(auto-fill, 260px);">
            @foreach ($completedTests as $test)
                <x-home.practice-test-card :test="$test" />
            @endforeach
        </div>
    </div>

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof window.initPracticeDashboardPage === 'function') {
                    window.initPracticeDashboardPage();
                }
            });
        </script>
    </x-slot>
</x-layouts.app-progress>
