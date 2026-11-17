<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('franchise_transfers', function (Blueprint $table) {
            $table->id();

            // Related amendment
            $table->foreignId('franchise_amendment_id')
                ->unique()
                ->constrained('franchise_amendments')
                ->restrictOnDelete();

            // Previous franchise owner
            $table->foreignId('previous_owner_id')
                ->constrained('franchise_owners')
                ->restrictOnDelete();

            // New franchise owner
            $table->foreignId('new_owner_id')
                ->constrained('franchise_owners')
                ->restrictOnDelete();

            // Effective date of ownership transfer
            $table->date('transfer_date')->nullable();

            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('franchise_transfers');
    }
};
