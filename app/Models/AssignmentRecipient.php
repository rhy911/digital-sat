<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentRecipient extends Model
{
    protected $fillable = ['assignment_id', 'student_id', 'status', 'assigned_at', 'withdrawn_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
}
