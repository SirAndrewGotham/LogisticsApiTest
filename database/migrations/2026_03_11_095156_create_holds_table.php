<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the `holds` table for tracking slot reservations.
     *
     * Columns:
     * - `id` (primary key)
     * - `slot_id` (foreign key to `slots`, cascades on delete)
     * - `user_id` (unsigned big integer)
     * - `status` (enum: 'held', 'confirmed', 'cancelled', 'expired'; default 'held')
     * - `idempotency_key` (string(64), unique)
     * - `expires_at` (timestamp)
     * - `created_at` and `updated_at`
     *
     * Indexes:
     * - composite index on (`slot_id`, `status`)
     * - index on `expires_at`
     * - index on `idempotency_key`
     */
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('user_id'); // I do not have users, adjust type if you have users table
            $table->enum('status', ['held', 'confirmed', 'cancelled', 'expired'])
                ->default('held');
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            // Composite index for frequent queries
            $table->index(['slot_id', 'status']);
            $table->index(['expires_at']);
            $table->index(['idempotency_key']);
        });
    }

    /**
     * Drop the 'holds' table if it exists.
     */
    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
