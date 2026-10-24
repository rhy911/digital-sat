<section class="study-discovery" aria-labelledby="discovery-title">
    <div><h2 id="discovery-title">Explore your study tools</h2><p>Everything you need for your next step.</p></div>
    <div class="study-discovery__grid">
        <article class="study-panel"><x-ui.icon name="compass" class="study-tool-icon" /><h3>Test Preview</h3><p>Explore sample questions and testing tools without timing or a scored result.</p><p class="study-caption">Desmos · Reference sheet · Answer tools</p><a class="study-link" href="{{ route('test.preview') }}">Launch Test Preview →</a></article>
        <article class="study-panel"><x-ui.icon name="journal-text" class="study-tool-icon" /><h3>Practice</h3><p>Browse available tests and choose a suitable practice format.</p><p class="study-caption">Full adaptive tests or focused sections</p><a class="study-link" href="{{ route('home.practice') }}">Explore test library →</a></article>
        <article class="study-panel"><x-ui.icon name="mortarboard" class="study-tool-icon" /><h3>Classes &amp; Assignments</h3>
            @if($canUseTeacherWorkspace)
                <p>Manage your classes and review assigned work.</p><p class="study-caption">{{ $teacherStats['activeClassesCount'] ?? 0 }} active {{ \Illuminate\Support\Str::plural('class', $teacherStats['activeClassesCount'] ?? 0) }}</p><div class="study-home__actions"><a class="study-link" href="{{ route('teacher.classes.index') }}">My classes →</a><a class="study-link" href="{{ route('teacher.assignments.index') }}">Assignments →</a></div>
            @else
                <p>Join a teacher’s class, find assigned work, and access class materials.</p><p class="study-caption">{{ $primaryClassroom?->name ?? 'Have a class code? Join your teacher’s class.' }}</p><div class="study-home__actions"><a class="study-link" href="{{ route('student.classes.index') }}">My classes →</a><a class="study-link" href="{{ route('student.assignments.index') }}">View assignments →</a></div>
            @endif
        </article>
        <article class="study-panel"><x-ui.icon name="graph-up-arrow" class="study-tool-icon" /><h3>Scores &amp; Progress</h3><p>Review your answers and see how your practice adds up.</p><p class="study-caption">{{ $completedCount }} completed practice {{ \Illuminate\Support\Str::plural('session', $completedCount) }}</p><div class="study-home__actions"><a class="study-link" href="{{ route('student.scores.index') }}">Score history →</a><a class="study-link" href="{{ route('student.progress') }}">Detailed progress →</a></div></article>
        <article class="study-panel"><x-ui.icon name="chat-left-quote" class="study-tool-icon" /><h3>Guides &amp; Community</h3><p>Read teacher articles or ask questions in the forum.</p><div class="study-home__actions"><a class="study-link" href="{{ route('blog.index') }}">Read articles →</a><a class="study-link" href="{{ route('forum.index') }}">Community forum →</a></div></article>
    </div>
</section>
@include('student.analytics.partials.home-feeds')
