@props(['tests', 'questions', 'questionsTotal'])

<div class="tab-pane fade" id="questions" role="tabpanel">
    <x-test-dashboard.questions.import-wizard :tests="$tests" />
    <x-test-dashboard.questions.validation-grid />
    <x-test-dashboard.questions.attach-question :tests="$tests" />
    <x-test-dashboard.questions.pool-table :tests="$tests" :questions="$questions" :questions-total="$questionsTotal" />
</div>
