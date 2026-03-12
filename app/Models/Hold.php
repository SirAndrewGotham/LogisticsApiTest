<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hold extends Model
{
    /** @use HasFactory<\Database\Factories\HoldFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slot_id',
        'status',
        'idempotency_key',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the slot that owns the hold.
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Determine whether the hold's expiration time is in the past.
     *
     * @return bool `true` if the hold's `expires_at` is before now, `false` otherwise.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Set the hold's status to 'confirmed' and persist the change to the database.
     */
    public function markAsConfirmed(): void
    {
        $this->update(['status' => 'confirmed']);
    }

    /**
     * Mark the hold's status as cancelled.
     *
     * Sets the model's `status` attribute to `'cancelled'` and persists the change.
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Mark the hold as expired and persist the status change to storage.
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }
}
