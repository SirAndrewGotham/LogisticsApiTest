<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slot extends Model
{
    /** @use HasFactory<\Database\Factories\SlotFactory> */
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (Slot $slot) {
            // Application-level check constraint
            if ($slot->remaining < 0) {
                throw new \RuntimeException('Remaining cannot be negative');
            }

            if ($slot->remaining > $slot->capacity) {
                throw new \RuntimeException('Remaining cannot exceed capacity');
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'capacity',
        'remaining',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capacity' => 'integer',
        'remaining' => 'integer',
    ];

    /**
     * Get the holds for the slot.
     */
    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    /**
     * Scope a query to only include available slots.
     */
    public function scopeAvailable($query): void
    {
        $query->where('remaining', '>', 0);
    }

    /**
     * Decrement remaining capacity atomically.
     *
     * @return bool True if a row was affected, false otherwise.
     */
    public function decrementRemaining(): bool
    {
        return (bool) $this->where('id', $this->id)
            ->where('remaining', '>', 0)
            ->decrement('remaining') > 0;
    }

    /**
     * Increment remaining capacity atomically.
     *
     * @return bool True if a row was affected, false otherwise.
     */
    public function incrementRemaining(): bool
    {
        return (bool) $this->where('id', $this->id)
            ->where('remaining', '<', $this->capacity)
            ->increment('remaining') > 0;
    }
}
