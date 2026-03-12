<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
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

    /**
    * Reverse the migrations.
    */
    public function down(): void
    {
        Schema::dropIfExists('slots');
        // Note: Dropping the table will automatically remove any CHECK constraints
    }
};
