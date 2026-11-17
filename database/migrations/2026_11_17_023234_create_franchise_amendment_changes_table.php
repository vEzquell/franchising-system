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
        Schema::create('franchise_amendment_changes', function (Blueprint $table) {
            $table->id();

            // Related amendment
            $table->foreignId('franchise_amendment_id')
                ->constrained('franchise_amendments')
                ->restrictOnDelete();

            // Field being amended
            $table->string('field_name', 100);

            // Original and proposed values
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            $table->timestamps();

            // Prevent duplicate field changes within one amendment
            $table->unique(
                ['franchise_amendment_id', 'field_name'],
                'unique_amendment_field'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_amendment_changes');
    }
};
