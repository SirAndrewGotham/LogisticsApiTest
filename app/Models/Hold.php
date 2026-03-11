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
     * Only allowed when status is 'held'.
     */
    public function markAsConfirmed(): bool
    {
        if ($this->status !== 'held' || $this->isExpired())
        {
            return false;
        }

        return (bool) static::where('id', $this->id)
            ->where('status', 'held')
            ->update(['status' => 'confirmed']);
    }

    /**
     * Mark hold as cancelled.
     * Only allowed when status is 'held'.
     */
    public function markAsCancelled(): bool
    {
        if ($this->status !== 'held')
        {
            return false;
        }

        return (bool) static::where('id', $this->id)
            ->where('status', 'held')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Mark hold as expired.
     * Only allowed when status is 'held'.
     */
    public function markAsExpired(): bool
    {
        if ($this->status !== 'held' || ! $this->isExpired())
        {
            return false;
        }

        return (bool) static::where('id', $this->id)
            ->where('status', 'held')
            ->update(['status' => 'expired']);
    }
}
