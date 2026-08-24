<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uid', 'occurrence_key', 'title', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'status'])]
class SourceEvent extends Model
{
    /** @return BelongsTo<CalendarSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CalendarSource::class, 'calendar_source_id');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
        ];
    }
}
