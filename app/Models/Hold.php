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
     * Get the Slot associated with this Hold.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo BelongsTo relationship to the Slot model.
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Constrain the query to holds that are currently active (status 'held' and expiration in the future).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The Eloquent query builder to scope.
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
     * Determine whether the hold is currently expired.
     *
     * @return bool `true` if the hold's status is "held" and its `expires_at` is less than or equal to the current time, `false` otherwise.
     */
    public function isExpired(): bool
    {
        return $this->status === 'held' && $this->expires_at <= now();
    }

    /**
     * Set the hold's status to "confirmed".
     *
     * @return bool `true` if the status was successfully updated to "confirmed", `false` otherwise.
     */
    public function markAsConfirmed(): bool
    {
        return $this->update(['status' => 'confirmed']);
    }

    /**
     * Set the hold's status to "cancelled".
     *
     * @return bool `true` if the model was successfully updated, `false` otherwise.
     */
    public function markAsCancelled(): bool
    {
        return $this->update(['status' => 'cancelled']);
    }

    /**
     * Set the hold's status to "expired".
     *
     * @return bool `true` if the model was successfully updated, `false` otherwise.
     */
    public function markAsExpired(): bool
    {
        return $this->update(['status' => 'expired']);
    }
}
