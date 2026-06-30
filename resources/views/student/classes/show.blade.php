<x-layouts.student :user="$user" :title="$classroom->name" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush

    <div class="student-workspace">
        <a class="back-link" href="{{ route('student.classes.index') }}">Back to classes</a>

        <div class="page-heading">
            <div>
                <span class="status-chip status-chip--{{ $classroom->status }}">{{ ucfirst($classroom->status) }}</span>
                <h1>{{ $classroom->name }}</h1>
                <p>{{ $classroom->description ?: 'Study resources and assigned work from your teaching team.' }}</p>
            </div>
            <div class="class-heading-actions">
                <a class="class-button class-button--primary" wire:navigate
                    href="{{ route('student.assignments.index', ['classroom' => $classroom->id]) }}">Assignments</a>
                @if ($membership)
                    <form method="POST" action="{{ route('student.classes.leave', $membership) }}"
                        onsubmit="return confirm('Are you sure you want to leave this class? All assignment histories will remain, but you will lose access to class materials.')">
                        @csrf
                        <button type="submit" class="class-button class-button--danger">Leave class</button>
                    </form>
                @endif
            </div>
        </div>

        <section class="class-panel" aria-labelledby="student-class-team">
            <div class="section-heading section-heading--tight">
                <div>
                    <h2 id="student-class-team">Teaching team</h2>
                    <p>Your class owner and co-teachers.</p>
                </div>
            </div>
            <div class="teacher-team-list">
                <div class="teacher-team-row">
                    <div>
                        <strong>{{ $classroom->owner->name }}</strong>
                        <span>{{ $classroom->owner->email }}</span>
                    </div>
                    <span class="status-chip">Owner</span>
                </div>
                @foreach ($classroom->coTeachers as $teacher)
                    <div class="teacher-team-row">
                        <div>
                            <strong>{{ $teacher->name }}</strong>
                            <span>{{ $teacher->email }}</span>
                        </div>
                        <span class="status-chip">Co-teacher</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="class-panel" aria-labelledby="student-class-documents">
            <div class="section-heading section-heading--tight">
                <div>
                    <h2 id="student-class-documents">Study documents</h2>
                    <p>Reference files and links shared for this class.</p>
                </div>
                <span>{{ $classroom->documents_count }} total</span>
            </div>

            @forelse($classroom->documents as $document)
                @php
                    $ext = $document->isFile() ? strtolower(pathinfo($document->original_name ?? '', PATHINFO_EXTENSION)) : '';
                @endphp
                <div class="document-row">
                    <div class="document-row__left">
                        <div class="document-row__icon-container document-row__icon--{{ $document->isFile() ? ($ext ?: 'file') : 'link' }}">
                            @if ($document->isFile())
                                @if ($ext === 'pdf')
                                    <!-- PDF icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                                @elseif (in_array($ext, ['doc', 'docx']))
                                    <!-- Word doc icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 2.8 2.8L6 22l-3 1 1-3Z"/></svg>
                                @elseif (in_array($ext, ['ppt', 'pptx']))
                                    <!-- PPT/Presentation icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/></svg>
                                @elseif (in_array($ext, ['xls', 'xlsx']))
                                    <!-- Spreadsheet icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 12h8"/><path d="M8 16h8"/><path d="M12 12v8"/></svg>
                                @elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'webp']))
                                    <!-- Image icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                @else
                                    <!-- Generic document file icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                                @endif
                            @else
                                <!-- External Web link icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            @endif
                        </div>
                        <div class="document-row__details">
                            <div class="document-row__header">
                                <strong class="document-row__title">{{ $document->title }}</strong>
                                @if ($document->isFile())
                                    <span class="document-row__badge document-row__badge--file">{{ strtoupper($ext ?: 'file') }}</span>
                                @else
                                    <span class="document-row__badge document-row__badge--link">LINK</span>
                                @endif
                            </div>
                            @if ($document->description)
                                <p class="document-row__description">{{ $document->description }}</p>
                            @endif
                            <div class="document-row__meta">
                                @if ($document->isFile())
                                    <span class="document-row__meta-item" title="Filename: {{ $document->original_name }}">{{ Str::limit($document->original_name, 28) }}</span>
                                    @if ($document->displaySize())
                                        <span class="document-row__meta-dot">&middot;</span>
                                        <span class="document-row__meta-item">{{ $document->displaySize() }}</span>
                                    @endif
                                @else
                                    <span class="document-row__meta-item">{{ parse_url($document->external_url ?? '', PHP_URL_HOST) }}</span>
                                @endif
                                <span class="document-row__meta-dot">&middot;</span>
                                <span class="document-row__meta-item">Shared {{ $document->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="row-actions">
                        @if ($document->isFile())
                            <a class="class-button" href="{{ route('class-documents.open', $document) }}" target="_blank"
                                rel="noopener">Open</a>
                            <a class="text-button" href="{{ route('class-documents.download', $document) }}">Download</a>
                        @else
                            <a class="class-button" href="{{ $document->external_url }}" target="_blank"
                                rel="noopener noreferrer">Open link</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="document-empty-state">
                    <div class="document-empty-state__graphic">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="9" cy="13" r="1"/><circle cx="15" cy="13" r="1"/><path d="M8 17s1 1.5 4 1.5 4-1.5 4-1.5"/></svg>
                    </div>
                    <h3>No study documents</h3>
                    <p>Your teaching team hasn't shared any study documents or reading links yet.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.student>
