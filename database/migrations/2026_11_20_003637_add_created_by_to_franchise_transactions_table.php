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
        $hasCreatedBy = Schema::hasColumn('franchise_transactions', 'created_by');

        if ($hasCreatedBy && Schema::hasForeignKey('franchise_transactions', ['created_by'])) {
            return;
        }

        Schema::table('franchise_transactions', function (Blueprint $table) use ($hasCreatedBy): void {
            if (! $hasCreatedBy) {
                $table->foreignId('created_by')->nullable();
            }

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('franchise_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
