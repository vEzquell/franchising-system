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
        Schema::create('franchise_substitutions', function (Blueprint $table) {
            $table->id();

            // Parent transaction
            $table->foreignId('franchise_transaction_id')
                ->unique()
                ->constrained('franchise_transactions')
                ->restrictOnDelete();

            // Vehicle currently assigned to the franchise
            $table->foreignId('previous_vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            // Replacement vehicle
            $table->foreignId('new_vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            // Effective date of substitution
            $table->date('substitution_date')->nullable();

            // Reason for replacing the vehicle
            $table->text('reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_substitutions');
    }
};
