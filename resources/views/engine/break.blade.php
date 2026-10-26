<x-layouts.test :pageTitle="$testData->page_title" :sectionTitle="$testData->section_title" :sectionNumber="$sectionNumber" :moduleNumber="$moduleNumber" :sectionName="$sectionName"
    :sectionType="$sectionType" :username="$testData->username" :isTeacherPreview="false" :testData="$testData">

    @push('scripts')
        <script>
            window.isBreakRecoveryView = true;
            window.userTestId = {{ $userTest->id }};
            window.nextModuleId = {{ $module->id }};
            window.nextModuleUrl = "{{ $nextModuleUrl }}";
            window.breakDurationSeconds = {{ $breakDurationSeconds }};
            window.remainingBreakSeconds = {{ $remainingBreakSeconds }};
        </script>
    @endpush
</x-layouts.test>
