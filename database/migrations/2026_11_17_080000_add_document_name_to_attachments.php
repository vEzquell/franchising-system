<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('document_name')->nullable()->after('document_type');
        });
        DB::table('attachments')->update(['document_name' => DB::raw('original_name')]);
    }

    public function down(): void
    {
        Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('document_name'));
    }
};
