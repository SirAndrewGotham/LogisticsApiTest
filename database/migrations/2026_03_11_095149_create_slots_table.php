<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the 'slots' table with an auto-incrementing primary key, unsigned integer
     * `capacity` and `remaining` columns (both default to 0), timestamp columns, and an
     * index on the `remaining` column.
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
    }
};
