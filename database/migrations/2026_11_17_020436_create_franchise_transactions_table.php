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
        Schema::create('franchise_transactions', function (Blueprint $table) {
            $table->id();

            // Nullable for new franchise applications
            $table->foreignId('franchise_id')
                ->nullable()
                ->constrained('franchises')
                ->restrictOnDelete();

            // Main transaction category
            $table->enum('transaction_type', [
                'application',
                'renewal',
                'substitution',
                'amendment',
            ]);

            // Processing status
            $table->enum('status', [
                'pending',
                'under_review',
                'approved',
                'rejected',
                'cancelled',
            ])->default('pending');

            // Date submitted to the office
            $table->date('date_filed');

            // Date and time approved
            $table->timestamp('approved_at')->nullable();

            // Staff who last processed the transaction
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Additional information
            $table->text('remarks')->nullable();

            // Index for faster searching/filtering
            $table->index([
                'franchise_id',
                'transaction_type',
                'status',
            ], 'franchise_transaction_lookup_idx');

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_transactions');
    }
};
