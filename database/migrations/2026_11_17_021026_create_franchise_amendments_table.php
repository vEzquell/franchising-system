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
        Schema::create('franchise_amendments', function (Blueprint $table) {
            $table->id();

            // Parent transaction
            $table->foreignId('franchise_transaction_id')
                ->unique()
                ->constrained('franchise_transactions')
                ->restrictOnDelete();

            // Type of amendment
            $table->enum('amendment_type', [
                'transfer_ownership',
                'change_route',
                'change_details',
            ]);

            // Amendment information
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_amendments');
    }
};
