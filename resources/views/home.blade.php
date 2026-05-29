<x-layouts.app :user="$user">
    @push('styles')
        @vite(['resources/css/home.css'])
    @endpush
    <div class="welcome">
        <div class="md:container md:mx-auto px-4 pb-6">
            <h1 class="text-3xl md:text-4xl font-normal text-[#324dc7]">Welcome, {{ $user->username ?? 'User' }}! Good
                luck on test day!</h1>
        </div>
    </div>
    <div class="md:container md:mx-auto px-4">
        <section class="your-tests">
            <x-home.tests-toggle-header />
            <x-home.empty-state-box title="You Have No Upcoming Tests" id="active-tests">
                <p class="text-lg">Tests appear here a few weeks before test day. <strong>If you got a paper ticket from
                        your school, <a class="text-[#324dc7] underline cursor-pointer"
                            onclick="event.preventDefault(); const btn = document.querySelector('header form button[type=\'submit\']') || document.querySelector('form button[type=\'submit\']'); if(btn) btn.click(); else document.querySelector('form')?.submit();">sign
                            out</a> and sign in with
                        it.</strong></p>
            </x-home.empty-state-box>
            <x-home.empty-state-box title="You Haven't Taken Any Digital Tests Yet" class="hidden" id="past-tests">
                <p class="text-lg">After you take a test, it will appear here with your scores and feedback.</p>
            </x-home.empty-state-box>
        </section>

        <section>
            <x-home.practice-toggle-header />
            <div class=" flex gap-4" id="practice-active">
                <x-home.practice-option-link :href="route('test.preview')" :image="asset('images/test_preview.png')"
                    alt="Test Preview" title="Test Preview" />
                <x-home.practice-option-link :href="route('choose-test')" :image="asset('images/test.png')"
                    alt="Full-Length Practice" title="Full-Length Practice" />
            </div>
            <div class="flex gap-4 hidden" id="practice-past">
                @forelse ($completedTests as $userTest)
                    <x-home.completed-practice-card :user-test="$userTest" compact />
                @empty
                    <x-home.empty-state-box title="Ready to Practice?">
                        <p class="text-lg">Go to <strong>Active</strong> and select <strong>Full-Length Practice</strong>.
                        </p>
                        <p class="text-lg">Once you take any full-length practice test, it will appear here with your scores
                            and
                            feedback.</p>
                    </x-home.empty-state-box>
                @endforelse
            </div>
        </section>

        <x-home.bigfuture-section />
    </div>

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof window.initHomeDashboardPage === 'function') {
                    window.initHomeDashboardPage();
                }
            });
        </script>
    </x-slot>
</x-layouts.app>