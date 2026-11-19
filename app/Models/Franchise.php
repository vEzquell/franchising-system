<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Franchise extends Model
{
    protected $fillable = [
        'franchise_number',
        'status',
        'date_issued',
        'expiration_date',
    ];

    protected function casts(): array
    {
        return [
            'date_issued' => 'date',
            'expiration_date' => 'date',
        ];
    }

    protected function status(): Attribute
    {
        return Attribute::make(get: function (?string $status): ?string {
            if ($status !== 'active') {
                return $status;
            }

            $expirationDate = $this->expiration_date?->toDateString();

            return $expirationDate !== null && $expirationDate < self::expirationCutoff() ? 'expired' : $status;
        });
    }

    protected function recordedStatus(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->attributes['status'] ?? null);
    }

    #[Scope]
    protected function whereEffectiveStatus(Builder $query, string $status): Builder
    {
        $cutoff = self::expirationCutoff();

        return match ($status) {
            'active' => $query->where('status', 'active')->where(fn (Builder $active) => $active
                ->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', $cutoff)),
            'expired' => $query->where(fn (Builder $expired) => $expired->where('status', 'expired')
                ->orWhere(fn (Builder $active) => $active->where('status', 'active')->whereDate('expiration_date', '<', $cutoff))),
            default => $query->where('status', $status),
        };
    }

    private static function expirationCutoff(): string
    {
        return today('Asia/Manila')->toDateString();
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(FranchiseOwnership::class, 'franchise_id');
    }

    public function liner(): BelongsTo
    {
        return $this->belongsTo(Liner::class);
    }

    public function renewals(): HasManyThrough
    {
        return $this->hasManyThrough(
            FranchiseRenewal::class,
            FranchiseTransaction::class,
            'franchise_id',
            'franchise_transaction_id',
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FranchiseTransaction::class, 'franchise_id');
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(FranchiseVehicleAssignment::class, 'franchise_id');
    }
}
