<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomNote extends Model
{
    protected $fillable = ['classroom_id', 'body', 'created_by'];

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
