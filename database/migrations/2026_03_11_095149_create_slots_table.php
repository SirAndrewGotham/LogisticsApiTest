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

        DB::statement("
            ALTER TABLE slots
            ADD CONSTRAINT slots_remaining_lte_capacity_chk
            CHECK (remaining <= capacity)
        ");
    }
};
