<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomMembership extends Model
{
    protected $fillable = ['classroom_id', 'student_id', 'display_name', 'status', 'requested_at', 'decided_at', 'ended_at', 'decided_by'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
