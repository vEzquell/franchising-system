<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchise_vehicle_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();
            $table->index(['franchise_id', 'ended_at']);
            $table->index(['vehicle_id', 'ended_at']);
        });
        Schema::create('franchise_transaction_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('franchise_owner_id')->nullable()->constrained()->restrictOnDelete();
            // Requested details are a snapshot; approval must apply them explicitly.
            $table->json('vehicle_details');
            $table->timestamps();
        });
        Schema::create('transaction_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_transaction_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 100);
            $table->string('original_name');
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_attachments');
        Schema::dropIfExists('franchise_transaction_vehicles');
        Schema::dropIfExists('franchise_vehicle_assignments');
    }
};
