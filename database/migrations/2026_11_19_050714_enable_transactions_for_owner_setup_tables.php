<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $connection = DB::connection();
        $engines = array_column(Schema::getTables($connection->getDatabaseName()), 'engine', 'name');
        foreach (['franchise_owners', 'franchises', 'franchise_ownerships', 'vehicles', 'franchise_vehicle_assignments', 'attachments'] as $table) {
            $engine = $engines[$connection->getTablePrefix().$table] ?? '';
            if (strcasecmp($engine, 'InnoDB') !== 0) {
                $quotedTable = $connection->getQueryGrammar()->wrapTable($table);
                DB::statement("ALTER TABLE {$quotedTable} ENGINE=InnoDB");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep transactional storage when rolling back application migrations.
    }
};
