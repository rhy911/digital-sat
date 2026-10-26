<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditClassroomAccess extends Command
{
    protected $signature = 'classrooms:audit-access {--output= : Optional CSV path; defaults to stdout}';

    protected $description = 'Report classroom links and assignment audiences that need access review without changing records';

    public function handle(): int
    {
        $path = $this->option('output');
        $stream = $path ? @fopen($path, 'wb') : STDOUT;
        if (! $stream) {
            $this->error('Could not open CSV output path.');

            return self::FAILURE;
        }

        fputcsv($stream, ['issue_type', 'classroom_id', 'test_id', 'assignment_id', 'student_id', 'reason', 'review_classification']);
        $write = static fn (array $row) => fputcsv($stream, $row);

        $links = DB::table('classroom_tests')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_tests.classroom_id')
            ->leftJoin('tests', 'tests.id', '=', 'classroom_tests.test_id')
            ->select(['classrooms.id as classroom_id', 'classrooms.owner_id', 'classroom_tests.test_id', 'tests.created_by', 'tests.status', 'tests.deleted_at'])
            ->orderBy('classrooms.id')->orderBy('classroom_tests.test_id')->get();
        $coTeachers = DB::table('classroom_teachers')->get()->groupBy('classroom_id');
        $shares = DB::table('test_shares')->get()->groupBy('test_id');

        foreach ($links as $link) {
            $classroomTeacherIds = collect([$link->owner_id])
                ->merge(($coTeachers[$link->classroom_id] ?? collect())->pluck('teacher_id'))
                ->map(fn ($id) => (int) $id)->unique();
            $available = $link->created_by !== null && $link->deleted_at === null && $link->status === 'active';
            $assignable = $available && ($classroomTeacherIds->contains((int) $link->created_by)
                || ($shares[$link->test_id] ?? collect())->contains(fn ($share) => $classroomTeacherIds->contains((int) $share->user_id)));
            if (! $available || ! $assignable) {
                $write([
                    ! $available ? 'linked_test_unavailable' : 'linked_test_not_assignable',
                    $link->classroom_id, $link->test_id, '', '',
                    ! $available ? 'Linked test is missing, deleted, or not active.' : 'No current classroom owner or co-teacher can assign this active test.',
                    'review',
                ]);
            }
        }

        $assignments = DB::table('assignments')
            ->join('classrooms', 'classrooms.id', '=', 'assignments.classroom_id')
            ->whereNull('assignments.deleted_at')
            ->select(['assignments.id', 'assignments.classroom_id', 'assignments.test_id', 'assignments.target_audience'])
            ->orderBy('assignments.id')->get();
        $activeRoster = DB::table('classroom_memberships')->where('status', 'active')->get()->groupBy('classroom_id');
        $recipients = DB::table('assignment_recipients')->where('status', 'active')->get()->groupBy('assignment_id');

        foreach ($assignments as $assignment) {
            $rosterIds = ($activeRoster[$assignment->classroom_id] ?? collect())->pluck('student_id')->map(fn ($id) => (int) $id)->unique();
            $activeRecipients = $recipients[$assignment->id] ?? collect();
            foreach ($activeRecipients as $recipient) {
                if (! $rosterIds->contains((int) $recipient->student_id)) {
                    $write([
                        'active_recipient_outside_roster', $assignment->classroom_id, $assignment->test_id,
                        $assignment->id, $recipient->student_id,
                        'Recipient is active but is not an active classroom member.', 'review',
                    ]);
                }
            }
            $recipientIds = $activeRecipients->pluck('student_id')->map(fn ($id) => (int) $id)->unique();
            if ($assignment->target_audience === 'specific' && $rosterIds->isNotEmpty()
                && $rosterIds->diff($recipientIds)->isEmpty()) {
                $write([
                    'specific_audience_covers_roster', $assignment->classroom_id, $assignment->test_id,
                    $assignment->id, '', 'Specific-audience recipients cover the entire current active roster.', 'needs review',
                ]);
            }
        }

        if ($path) {
            fclose($stream);
            $this->info("Read-only classroom access audit written to {$path}");
        }

        return self::SUCCESS;
    }
}
