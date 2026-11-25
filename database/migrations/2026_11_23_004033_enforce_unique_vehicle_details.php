<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace missing identifiers for indexing and record which ones were null.
     */
    private const string VEHICLE_DETAILS = "make_type, (COALESCE(plate_number, '')), (COALESCE(motor_number, '')), (COALESCE(chassis_number, '')), ((plate_number IS NULL) + 2 * (motor_number IS NULL) + 4 * (chassis_number IS NULL))";

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $details = self::VEHICLE_DETAILS;

        if (DB::getDriverName() === 'sqlite') {
            DB::transaction(function () use ($details): void {
                DB::statement("CREATE UNIQUE INDEX vehicles_details_unique ON vehicles ($details)");

                Schema::table('vehicles', function (Blueprint $table): void {
                    $table->dropUnique(['plate_number', 'make_type']);
                    $table->dropUnique(['motor_number']);
                    $table->dropUnique(['chassis_number']);
                });
            });

            return;
        }

        DB::statement(<<<SQL
            ALTER TABLE vehicles
                ADD UNIQUE INDEX vehicles_details_unique ($details),
                DROP INDEX vehicles_plate_number_make_type_unique,
                DROP INDEX vehicles_motor_number_unique,
                DROP INDEX vehicles_chassis_number_unique
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::transaction(function (): void {
                Schema::table('vehicles', function (Blueprint $table): void {
                    $table->unique(['plate_number', 'make_type']);
                    $table->unique('motor_number');
                    $table->unique('chassis_number');
                    $table->dropUnique('vehicles_details_unique');
                });
            });

            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE vehicles
                ADD UNIQUE INDEX vehicles_plate_number_make_type_unique (plate_number, make_type),
                ADD UNIQUE INDEX vehicles_motor_number_unique (motor_number),
                ADD UNIQUE INDEX vehicles_chassis_number_unique (chassis_number),
                DROP INDEX vehicles_details_unique
            SQL);
    }
};
