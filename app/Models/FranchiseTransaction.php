<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FranchiseTransaction extends Model
{
    protected $fillable = [
        'franchise_id',
        'transaction_type',
        'status',
        'date_filed',
        'certificate_issue_date',
        'approved_at',
        'processed_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_filed' => 'date',
            'certificate_issue_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
        ];
    }

    public function nextStatuses(): array
    {
        return match ($this->status) {
            'pending' => ['under_review', 'approved', 'rejected', 'cancelled'],
            'under_review' => ['approved', 'rejected', 'cancelled'],
            default => [],
        };
    }

    public function attachmentOwnershipId(): ?int
    {
        if ($this->transaction_type === 'amendment' && ($transfer = $this->amendment?->transfer)) {
            if ($this->status !== 'approved' || $transfer->transfer_date === null) {
                return null;
            }

            $ownershipIds = FranchiseOwnership::query()
                ->where('franchise_id', $this->franchise_id)
                ->where('franchise_owner_id', $transfer->new_owner_id)
                ->whereDate('started_at', $transfer->transfer_date)
                ->limit(2)->pluck('id');

            return $ownershipIds->count() === 1 ? (int) $ownershipIds->sole() : null;
        }

        $filingDate = $this->date_filed->toDateString();
        $ownerId = match ($this->transaction_type) {
            'application' => $this->vehicleDetails?->franchise_owner_id,
            'renewal' => $this->renewal?->franchise_owner_id,
            default => null,
        };

        $ownershipIds = FranchiseOwnership::query()
            ->where('franchise_id', $this->franchise_id)
            ->when($ownerId !== null, fn ($query) => $query->where('franchise_owner_id', $ownerId))
            ->whereDate('started_at', '<=', $filingDate)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', $filingDate))
            ->limit(2)->pluck('id');

        return $ownershipIds->count() === 1 ? (int) $ownershipIds->sole() : null;
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class, 'franchise_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function renewal(): HasOne
    {
        return $this->hasOne(FranchiseRenewal::class, 'franchise_transaction_id');
    }

    public function amendment(): HasOne
    {
        return $this->hasOne(FranchiseAmendment::class, 'franchise_transaction_id');
    }

    public function substitution(): HasOne
    {
        return $this->hasOne(FranchiseSubstitution::class, 'franchise_transaction_id');
    }

    public function vehicleDetails(): HasOne
    {
        return $this->hasOne(FranchiseTransactionVehicle::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
