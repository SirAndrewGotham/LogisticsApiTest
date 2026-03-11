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
        'user_id',
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
     * Scope a query to only include active holds.
     */
    public function scopeActive($query)
    {
        $query->where('status', 'held')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include expired holds.
     */
    public function scopeExpired($query)
    {
        $query->where('status', 'held')
            ->where('expires_at', '<=', now());
    }

    /**
     * Check if the hold is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'held' && $this->expires_at <= now();
    }

    /**
     * Mark hold as confirmed.
     */
    public function markAsConfirmed(): bool
    {
        return $this->update(['status' => 'confirmed']);
    }

    /**
     * Mark hold as cancelled.
     */
    public function markAsCancelled(): bool
    {
        return $this->update(['status' => 'cancelled']);
    }

    /**
     * Mark hold as expired.
     */
    public function markAsExpired(): bool
    {
        return $this->update(['status' => 'expired']);
    }
}
