<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read ?Assignment $assignment
 * @property-read ?User $student
 */
class AssignmentRecipient extends Model
{
    protected $fillable = ['assignment_id', 'student_id', 'status', 'extra_attempts', 'assigned_at', 'withdrawn_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'withdrawn_at' => 'datetime', 'extra_attempts' => 'integer'];
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class); }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }

    public function maxAllowedAttempts(): int
    {
        return (int) (($this->assignment->attempt_limit ?? 1) + ($this->extra_attempts ?? 0));
    }
}
