<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassroomNote extends Model
{
    protected $fillable = ['classroom_id', 'body', 'created_by'];

    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
}
