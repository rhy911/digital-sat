@props([
    'announcement',
    'classroom',
    'canManage' => false,
])

@php
    $teacherIds = [$classroom->owner_id, ...$classroom->coTeachers->pluck('user_id')->all()];
@endphp

<article class="announce-card {{ $announcement->pinned ? 'is-pinned' : '' }}">
    <div class="announce-card__head">
        <div class="announce-card__meta">
            <span class="announce-avatar">{{ substr($announcement->author?->name ?? 'T', 0, 1) }}</span>
            <div>
                <div class="announce-card__author">{{ $announcement->author?->name ?? 'Teacher' }}</div>
                <div class="announce-card__time">{{ $announcement->created_at->diffForHumans() }}</div>
            </div>
            @if ($announcement->pinned)
                <span class="announce-pin-badge">📌 Pinned</span>
            @endif
        </div>
        @if ($canManage)
            <div class="announce-card__tools">
                <form method="POST"
                    action="{{ route('teacher.announcements.pin', [$classroom, $announcement]) }}">
                    @csrf
                    <button type="submit" class="announce-pin-btn">
                        {{ $announcement->pinned ? 'Unpin' : 'Pin' }}
                    </button>
                </form>
                <button type="button" @click="$dispatch('open-confirm-delete', {
                    title: 'Delete Announcement?',
                    message: 'Are you sure you want to delete this announcement? This action cannot be undone.',
                    actionUrl: '{{ route('teacher.announcements.destroy', [$classroom, $announcement]) }}'
                })" class="announce-del-btn">Delete</button>
            </div>
        @endif
    </div>
    <div class="announce-card__body">{!! nl2br(e($announcement->body)) !!}</div>

    <div class="announce-comments">
        <div class="announce-comment-list">
            @foreach ($announcement->comments as $comment)
                <div class="announce-comment">
                    <div class="announce-comment__left">
                        <span class="announce-comment__avatar">{{ substr($comment->author?->name ?? 'U', 0, 1) }}</span>
                        <div class="announce-comment__content">
                            <span class="announce-comment__author">{{ $comment->author?->name ?? 'User' }}</span>
                            @if (in_array($comment->author_id, $teacherIds, true))
                                <span class="announce-comment__role-tag">Teacher</span>
                            @endif
                            <span class="announce-comment__body">{{ $comment->body }}</span>
                            <div class="announce-comment__time">{{ $comment->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    @if ($canManage || $comment->author_id === auth()->id())
                        <div class="announce-comment__actions">
                            <button type="button" @click="$dispatch('open-confirm-delete', {
                                title: 'Delete Comment?',
                                message: '{{ $canManage ? 'Are you sure you want to delete this comment?' : 'Are you sure you want to delete your comment?' }}',
                                actionUrl: '{{ route('announcements.comments.destroy', [$classroom, $announcement, $comment]) }}'
                            })" class="announce-comment-del-btn"
                                title="{{ $canManage ? 'Delete comment' : 'Delete your comment' }}">&times;</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($classroom->status === 'active')
            <form method="POST"
                action="{{ route('announcements.comments.store', [$classroom, $announcement]) }}"
                class="announce-comment-form" x-data="{ commentText: '' }" autocomplete="off">
                @csrf
                <input type="text" name="body" maxlength="2000" required x-model="commentText" autocomplete="off"
                    placeholder="Write a comment…" class="announce-comment-input">
                <button type="submit" class="btn-sm-primary btn-compact announce-send-btn" :disabled="!commentText.trim()" :class="{ 'is-active': commentText.trim().length > 0 }">Send</button>
            </form>
        @endif
    </div>
</article>
