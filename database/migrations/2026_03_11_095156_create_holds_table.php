<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the `holds` table and its schema used to track slot reservations.
     *
     * The table includes an auto-incrementing `id`, a foreign key `slot_id`
     * referencing `slots.id` with cascade on delete, a `status` enum with values
     * `held`, `confirmed`, `cancelled`, and `expired` (default `held`), a unique
     * `idempotency_key` (string, 64 chars), a non-nullable `expires_at` timestamp,
     * and `created_at`/`updated_at` timestamps. Adds a composite index on
     * (`slot_id`, `status`) and an index on `expires_at`.
     */
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['held', 'confirmed', 'cancelled', 'expired'])
                ->default('held');
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('expires_at')->nullable(false);
            $table->timestamps();

            // Composite index for frequent queries
            $table->index(['slot_id', 'status']);
            $table->index(['expires_at']);
        });
    }
};
