@props(['tests', 'questions', 'questionsTotal'])

<div x-show="activeTab === 'questions'" id="questions" role="tabpanel" style="display: none;">
    <x-admin.test-builder.questions.import-wizard :tests="$tests" />
    <x-admin.test-builder.questions.validation-grid />
    <x-admin.test-builder.questions.pool-table :tests="$tests" :questions="$questions" :questions-total="$questionsTotal" />
</div>
