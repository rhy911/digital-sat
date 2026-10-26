<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** @return BelongsTo<Test, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    /** @return HasMany<ScoreConversion, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ScoreConversion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
