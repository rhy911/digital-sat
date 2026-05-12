<x-layouts.portal title="Test Preview" nextUrl="/take-test" backUrl="/home">
    @push('styles')
        <style>
            .info {
                display: flex;
                text-align: left;
                gap: 1rem;
                margin-bottom: 20px;
            }

            .icon {
                height: fit-content;
                display: flex;
                align-items: center;
                padding: 10px;
                border-radius: 50%;
                background-color: #d9d9d9;
            }

            .info h2 {
                font-size: 1.5rem;
                font-weight: 600;
            }

            .info p {
                font-size: 1rem;
            }
        </style>
    @endpush
    <x-portal.info-item title="Explore Bluebook" description="Sample questions from AP Exams or the SAT Suite, and try out the testing tools. You won't receive scores or any feedback on your answers.">
        <x-slot name="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="feather feather-search">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </x-slot>
    </x-portal.info-item>

    <x-portal.info-item title="Take Your Time" description="The sections in this preview are untimed. On test day, a timer will be running. If you're approved for extra time or breaks, you'll get that on test day.">
        <x-slot name="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="feather feather-clock">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </x-slot>
    </x-portal.info-item>

    <x-portal.info-item title="Assistive Technology (AT)" description="Be sure to practice with any AT you use for testing. If you configure your AT settings here, you may need to repeat this step on test day.">
        <x-slot name="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="feather feather-user-check">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <polyline points="17 11 19 13 23 9"></polyline>
            </svg>
        </x-slot>
    </x-portal.info-item>

    <x-portal.info-item title="No Device Lock" description="We don't lock your device on previews. On test day, you'll be blocked from using other programs or apps.">
        <x-slot name="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="feather feather-unlock">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
            </svg>
        </x-slot>
    </x-portal.info-item>
</x-layouts.portal>
