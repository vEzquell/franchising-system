<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make_type', 50);
            // Minimal identity required by substitutions; extend when registration requirements are defined.
            $table->string('plate_number', 20)->nullable()->unique();
            $table->string('motor_number', 100)->nullable()->unique();
            $table->string('chassis_number', 100)->nullable()->unique();
            // Recommendation: require at least one identifier and normalize identifiers before saving.
            // Add a dated franchise/vehicle assignment table if assignment history is required.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
