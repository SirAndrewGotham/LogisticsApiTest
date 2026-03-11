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
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
