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
     * Decrement remaining capacity atomically.
     *
     * @return bool True if a row was affected, false otherwise.
     */
    public function decrementRemaining(): bool
    {
        if ($this->remaining <= 0) {
            return false;
        }

        $this->remaining--;
        return $this->save();
    }

    public function incrementRemaining(): bool
    {
        if ($this->remaining >= $this->capacity) {
            return false;
        }

        $this->remaining++;
        return $this->save();
    }

}
