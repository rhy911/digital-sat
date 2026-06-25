<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_RW = 'reading_writing';
    public const TYPE_MATH = 'math';

    protected $fillable = [
        'test_id',
        'name',
        'type',
        'order',
        'created_by',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo($query, $user)
    {
        if (!$user) {
            return $query->where('is_public', true);
        }
        if ($user->role === 'admin') {
            return $query;
        }
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhere('is_public', true)
              ->orWhereHas('test', function ($t) use ($user) {
                  $t->where('created_by', $user->id)
                    ->orWhere('is_public', true)
                    ->orWhereHas('shares', fn ($shares) => $shares->where('user_id', $user->id));
              });
        });
    }

    public function test()
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'section_modules')
            ->orderBy('module_number')
            ->orderBy('order');
    }
}
