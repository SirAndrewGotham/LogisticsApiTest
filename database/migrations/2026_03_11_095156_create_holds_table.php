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

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
