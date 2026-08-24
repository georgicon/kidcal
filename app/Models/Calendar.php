<?php

namespace App\Models;

use Database\Factories\CalendarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'timezone', 'week_starts_on', 'editor_token_hash'])]
class Calendar extends Model
{
    /** @use HasFactory<CalendarFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CalendarSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(CalendarSource::class);
    }

    /** @return HasMany<ManualEvent, $this> */
    public function manualEvents(): HasMany
    {
        return $this->hasMany(ManualEvent::class);
    }

    protected function casts(): array
    {
        return [
            'week_starts_on' => 'integer',
        ];
    }
}
