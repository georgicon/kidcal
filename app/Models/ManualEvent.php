<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property array<int, int>|null $recurrence_weekdays
 * @property CarbonImmutable|null $recurrence_until
 */
#[Fillable(['title', 'location', 'starts_at', 'ends_at', 'all_day', 'color', 'recurrence_type', 'recurrence_interval', 'recurrence_weekdays', 'recurrence_until'])]
class ManualEvent extends Model
{
    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'recurrence_interval' => 'integer',
            'recurrence_weekdays' => 'array',
            'recurrence_until' => 'immutable_date',
        ];
    }
}
