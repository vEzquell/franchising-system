<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array BUILT_IN_TYPES = [
        'application' => 'Application',
        'renewal' => 'Renewal',
        'substitution' => 'Substitution',
        'amendment' => 'Amendment',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['franchise_transactions', 'requirements'] as $table) {
            if (DB::table($table)->whereNotIn('transaction_type', array_keys(self::BUILT_IN_TYPES))->exists()) {
                throw new RuntimeException("Unrecognized transaction type in {$table}.");
            }
        }

        Schema::create('transaction_types', function (Blueprint $table) {
            $table->string('code', 24)->primary();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (self::BUILT_IN_TYPES as $code => $name) {
            DB::table('transaction_types')->insert(['code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('franchise_transactions', function (Blueprint $table) {
            $table->string('transaction_type', 24)->change();
        });

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['franchise_transactions', 'requirements'] as $table) {
                Schema::table($table, function (Blueprint $table) {
                    $table->foreign('transaction_type')->references('code')->on('transaction_types')->restrictOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('transaction_types')->whereNotIn('code', array_keys(self::BUILT_IN_TYPES))->exists()) {
            throw new RuntimeException('Remove custom transaction types before rolling back this migration.');
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['requirements', 'franchise_transactions'] as $table) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['transaction_type']);
                });
            }
        }

        Schema::table('franchise_transactions', function (Blueprint $table) {
            $table->enum('transaction_type', array_keys(self::BUILT_IN_TYPES))->change();
        });

        Schema::dropIfExists('transaction_types');
    }
};
