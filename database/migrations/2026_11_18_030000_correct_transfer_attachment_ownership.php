<?php

use App\Models\FranchiseTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            FranchiseTransaction::query()
                ->where('transaction_type', 'amendment')
                ->whereHas('amendment.transfer')
                ->whereHas('attachments')
                ->with('amendment.transfer')
                ->chunkById(200, function ($transactions) {
                    foreach ($transactions as $transaction) {
                        $transaction->attachments()->update([
                            'franchise_ownership_id' => $transaction->attachmentOwnershipId(),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Keep corrected document links on rollback.
    }
};
