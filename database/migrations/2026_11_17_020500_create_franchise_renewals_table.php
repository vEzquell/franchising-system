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
        Schema::create('franchise_renewals', function (Blueprint $table) {
            $table->id();

            // Related renewal transaction
            $table->foreignId('franchise_transaction_id')
                ->unique()
                ->constrained('franchise_transactions')
                ->restrictOnDelete();

            // Owner requesting the renewal
            $table->foreignId('franchise_owner_id')
                ->constrained('franchise_owners')
                ->restrictOnDelete();

            // Renewal details
            $table->date('renewal_date')->nullable();

            $table->date('previous_expiration_date');
            $table->date('new_expiration_date');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_renewals');
    }
};
