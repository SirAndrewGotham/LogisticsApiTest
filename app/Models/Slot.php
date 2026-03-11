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
     * Register a model saving callback that enforces remaining is between 0 and capacity.
     *
     * @throws \RuntimeException If `remaining` is less than 0 or greater than `capacity`.
     * @return void
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
     * Get the holds associated with the slot.
     *
     * @return HasMany A HasMany relation for the slot's Hold models.
     */
    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    /**
         * Limit the query to slots that have remaining capacity greater than zero.
         *
         * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query The query builder instance to modify.
         */
    public function scopeAvailable($query): void
    {
        $query->where('remaining', '>', 0);
    }

    /**
         * Atomically decrement the slot's remaining capacity if it is greater than zero.
         *
         * @return bool `true` if the remaining value was decremented, `false` otherwise.
         */
    public function decrementRemaining(): bool
    {
        return $this->where('id', $this->id)
            ->where('remaining', '>', 0) // Prevents negative
            ->decrement('remaining');
    }

    /**
     * Atomically increments this slot's remaining count if it is less than capacity.
     *
     * @return bool `true` if the remaining value was incremented, `false` otherwise.
     */
    public function incrementRemaining(): bool
    {
        return $this->where('id', $this->id)
            ->where('remaining', '<', $this->capacity) // Prevents over-capacity
            ->increment('remaining');
    }
}
