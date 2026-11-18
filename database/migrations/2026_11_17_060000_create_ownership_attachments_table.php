<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_ownership_id')->constrained()->restrictOnDelete();
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
        Schema::dropIfExists('ownership_attachments');
    }
};
