<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Attachment extends Model
{
    protected $fillable = ['franchise_transaction_id', 'franchise_ownership_id', 'requirement_id', 'document_type', 'document_name', 'original_name', 'disk', 'path', 'mime_type', 'size'];

    protected $hidden = ['disk', 'path'];

    protected static function booted(): void
    {
        static::saving(function (Attachment $attachment) {
            if ($attachment->franchise_transaction_id === null && $attachment->franchise_ownership_id === null) {
                throw ValidationException::withMessages(['attachments' => 'Each attachment must belong to a transaction or an ownership record.']);
            }

            if ($attachment->franchise_transaction_id !== null && $attachment->franchise_ownership_id !== null) {
                $franchiseId = $attachment->transaction()->value('franchise_id');
                if ($franchiseId === null || ! $attachment->ownership()->where('franchise_id', $franchiseId)->exists()) {
                    throw ValidationException::withMessages(['attachments' => 'The transaction and ownership must belong to the same franchise.']);
                }
            }
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FranchiseTransaction::class, 'franchise_transaction_id');
    }

    public function ownership(): BelongsTo
    {
        return $this->belongsTo(FranchiseOwnership::class, 'franchise_ownership_id');
    }
}
