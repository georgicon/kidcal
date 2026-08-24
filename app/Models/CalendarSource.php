<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'url', 'normalized_url', 'color', 'enabled', 'sync_status', 'sync_error', 'etag', 'last_modified', 'last_synced_at'])]
class CalendarSource extends Model
{
    /** @return BelongsTo<Calendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /** @return HasMany<SourceEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SourceEvent::class);
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
