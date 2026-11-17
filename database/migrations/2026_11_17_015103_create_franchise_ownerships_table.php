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
        Schema::create('franchise_ownerships', function (Blueprint $table) {
            $table->id();

            // Franchise
            $table->foreignId('franchise_id')
                ->constrained('franchises')
                ->restrictOnDelete();

            // Franchise Owner
            $table->foreignId('franchise_owner_id')
                ->constrained('franchise_owners')
                ->restrictOnDelete();

            // Ownership period
            $table->date('started_at');
            $table->date('ended_at')->nullable();

            // Index for ownership lookup
            $table->index(['franchise_id', 'ended_at']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_ownerships');
    }
};
