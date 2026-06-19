@props(['tests', 'questions', 'questionsTotal'])

<div x-show="activeTab === 'questions'" id="questions" role="tabpanel" aria-labelledby="questions-tab"
    :aria-hidden="activeTab === 'questions' ? 'false' : 'true'" :class="{ 'active': activeTab === 'questions' }"
    class="tab-pane" style="display: none;">
    <x-admin.test-builder.questions.import-wizard :tests="$tests" />
    <x-admin.test-builder.questions.validation-grid />
    <x-admin.test-builder.questions.pool-table :tests="$tests" :questions="$questions" :questions-total="$questionsTotal" />
</div>
