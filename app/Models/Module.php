<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'module_number',
        'difficulty_level',
        'duration_minutes',
        'total_questions',
        'order',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'module_questions')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
