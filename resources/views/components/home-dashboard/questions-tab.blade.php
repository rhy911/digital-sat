@props(['tests', 'questions', 'questionsTotal'])

<div x-show="activeTab === 'questions'" id="questions" role="tabpanel" style="display: none;">
    <x-home-dashboard.questions.import-wizard :tests="$tests" />
    <x-home-dashboard.questions.validation-grid />
    <x-home-dashboard.questions.pool-table :tests="$tests" :questions="$questions" :questions-total="$questionsTotal" />
</div>
