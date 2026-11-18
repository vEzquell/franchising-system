<?php

use App\Models\FranchiseTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        FranchiseTransaction::query()
            ->with(['vehicleDetails', 'renewal', 'amendment.transfer'])
            ->whereHas('attachments', fn ($query) => $query->whereNull('franchise_ownership_id'))
            ->chunkById(200, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $ownershipId = $transaction->attachmentOwnershipId();
                    if ($ownershipId !== null) {
                        $transaction->attachments()->whereNull('franchise_ownership_id')
                            ->update(['franchise_ownership_id' => $ownershipId]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('attachments')->whereNotNull('franchise_transaction_id')
            ->whereNotNull('franchise_ownership_id')->update(['franchise_ownership_id' => null]);
    }
};
