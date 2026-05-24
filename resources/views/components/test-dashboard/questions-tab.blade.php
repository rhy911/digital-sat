@props(['tests', 'questions', 'questionsTotal'])

<div x-show="activeTab === 'questions'" id="questions" role="tabpanel" style="display: none;" x-transition.opacity.duration.300ms>
    <x-test-dashboard.questions.import-wizard :tests="$tests" />
    <x-test-dashboard.questions.validation-grid />
    <x-test-dashboard.questions.attach-question :tests="$tests" />
    <x-test-dashboard.questions.pool-table :tests="$tests" :questions="$questions" :questions-total="$questionsTotal" />
</div>
