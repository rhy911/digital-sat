{{-- Teacher-only switch between the two faces of /home. Rendered in both panes,
     so it lives here instead of being duplicated (and drifting) per pane.
     Relies on the `tab` state of the surrounding x-data on the page shell. --}}
<div class="home-switch" role="group" aria-label="Switch home view">
    <button type="button" class="home-switch__btn"
        :class="tab === 'overview' ? 'is-active' : ''"
        :aria-pressed="tab === 'overview' ? 'true' : 'false'"
        @click="tab = 'overview'; $dispatch('teacher-home-tab-requested', { tab: 'overview' })">
        Teacher overview
    </button>
    <button type="button" class="home-switch__btn"
        :class="tab === 'progress' ? 'is-active' : ''"
        :aria-pressed="tab === 'progress' ? 'true' : 'false'"
        @click="tab = 'progress'; $dispatch('teacher-home-tab-requested', { tab: 'progress' })">
        Student view
    </button>
</div>
