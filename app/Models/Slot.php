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
     * Register a model saving hook that ensures the slot's `remaining` is between 0 and `capacity`.
     *
     * Throws an InvalidArgumentException when `remaining` is less than 0 or greater than `capacity`.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (Slot $slot) {
            // Enforce remaining >= 0
            if ($slot->remaining < 0) {
                throw new \InvalidArgumentException('Remaining cannot be less than 0');
            }

            // Enforce remaining <= capacity
            if ($slot->remaining > $slot->capacity) {
                throw new \InvalidArgumentException('Remaining cannot exceed capacity');
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
     * Decreases the slot's remaining count by one and persists the change if remaining is greater than zero.
     *
     * @return bool `true` if the remaining value was decreased and the model saved successfully, `false` otherwise.
     */
    public function decrementRemaining(): bool
    {
        if ($this->remaining <= 0) {
            return false;
        }

        $this->remaining--;
        return $this->save();
    }

    /**
     * Increases the slot's remaining count by one if it is less than capacity and persists the change.
     *
     * @return bool `true` if remaining was increased and persisted, `false` otherwise.
     */
    public function incrementRemaining(): bool
    {
        if ($this->remaining >= $this->capacity) {
            return false;
        }

        $this->remaining++;
        return $this->save();
    }

}
