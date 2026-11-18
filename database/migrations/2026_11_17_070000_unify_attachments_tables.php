<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function columns(Blueprint $table): void
    {
        $table->string('document_type', 100);
        $table->string('original_name');
        $table->string('disk', 50)->default('local');
        $table->string('path');
        $table->string('mime_type', 100);
        $table->unsignedBigInteger('size');
        $table->timestamps();
    }

    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('franchise_ownership_id')->nullable()->constrained()->restrictOnDelete();
            $this->columns($table);
        });
        // Preserve existing paths: files do not need to be moved or uploaded again.
        DB::transaction(function () {
            foreach (['transaction_attachments', 'ownership_attachments'] as $source) {
                DB::table($source)->orderBy('id')->chunkById(200, function ($rows) use ($source) {
                    foreach ($rows as $row) {
                        $data = (array) $row;
                        if ($source === 'ownership_attachments') {
                            unset($data['id']);
                        }
                        DB::table('attachments')->insert($data);
                    }
                });
            }
        });
        Schema::drop('ownership_attachments');
        Schema::drop('transaction_attachments');
    }

    public function down(): void
    {
        foreach (['transaction_attachments' => 'franchise_transaction_id', 'ownership_attachments' => 'franchise_ownership_id'] as $tableName => $foreignKey) {
            Schema::create($tableName, function (Blueprint $table) use ($foreignKey) {
                $table->id();
                $table->foreignId($foreignKey)->constrained()->restrictOnDelete();
                $this->columns($table);
            });
            DB::table('attachments')->whereNotNull($foreignKey)->orderBy('id')->chunkById(200, function ($rows) use ($tableName, $foreignKey) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    unset($data[$foreignKey === 'franchise_transaction_id' ? 'franchise_ownership_id' : 'franchise_transaction_id']);
                    DB::table($tableName)->insert($data);
                }
            });
        }
        Schema::drop('attachments');
    }
};
