<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the `slots` table and add a database-level CHECK constraint for `remaining` when supported.
     *
     * The table contains `id`, `capacity` (unsigned integer, default 0), `remaining` (unsigned integer, default 0),
     * timestamp columns, and an index on `remaining`. When the connection driver supports ALTER TABLE CHECK constraints
     * (MySQL 8.0.16+ and PostgreSQL), adds a `slots_remaining_range_chk` constraint ensuring `remaining >= 0` and
     * `remaining <= capacity`. SQLite is left to enforce this rule at the application level.
     */
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('remaining')->default(0);
            $table->timestamps();

            $table->index('remaining');
        });

        // Only add CHECK constraint for databases that support it
        if (DB::connection()->getDriverName() === 'mysql') {
            // MySQL 8.0.16+ supports CHECK constraints
            DB::statement('ALTER TABLE slots ADD CONSTRAINT slots_remaining_range_chk CHECK (remaining >= 0 AND remaining <= capacity)');
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            // PostgreSQL supports CHECK constraints
            DB::statement('ALTER TABLE slots ADD CONSTRAINT slots_remaining_range_chk CHECK (remaining >= 0 AND remaining <= capacity)');
        }
        // SQLite doesn't support adding CHECK constraints via ALTER TABLE
        // So we enforce it at application level
    }
};
