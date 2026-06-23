<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreConversionSet extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'test_id',
        'version',
        'status',
        'source_name',
        'source_url',
        'notes',
        'checksum',
        'form_checksum',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    public function rows()
    {
        return $this->hasMany(ScoreConversion::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
