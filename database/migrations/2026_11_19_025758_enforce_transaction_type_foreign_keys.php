<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array REFERENCING_TABLES = ['franchise_transactions', 'requirements'];

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

        foreach (['transaction_types', ...self::REFERENCING_TABLES] as $table) {
            $engine = $engines[$connection->getTablePrefix().$table] ?? '';
            if (strcasecmp($engine, 'InnoDB') !== 0) {
                $quotedTable = $connection->getQueryGrammar()->wrapTable($table);
                DB::statement("ALTER TABLE {$quotedTable} ENGINE=InnoDB");
            }
        }

        foreach (self::REFERENCING_TABLES as $table) {
            if (! $this->hasTypeForeignKey($table)) {
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
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::REFERENCING_TABLES as $table) {
            if ($this->hasTypeForeignKey($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['transaction_type']);
                });
            }
        }
    }

    private function hasTypeForeignKey(string $table): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn (array $key) => $key['columns'] === ['transaction_type']);
    }
};
