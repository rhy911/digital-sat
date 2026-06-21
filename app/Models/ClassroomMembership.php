<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassroomMembership extends Model
{
    protected $fillable = ['classroom_id', 'student_id', 'status', 'requested_at', 'decided_at', 'ended_at', 'decided_by'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
