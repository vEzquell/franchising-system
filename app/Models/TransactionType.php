<?php

namespace App\Models;

use Database\Factories\TransactionTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TransactionType extends Model
{
    /** @use HasFactory<TransactionTypeFactory> */
    use HasFactory;

    public const array BUILT_IN_TYPES = [
        'application' => 'Application',
        'renewal' => 'Renewal',
        'substitution' => 'Substitution',
        'amendment' => 'Amendment',
    ];

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (TransactionType $type): void {
            $type->code ??= 'type_'.Str::lower(Str::random(19));
        });
    }

    public static function hasBuiltInWorkflow(mixed $code): bool
    {
        return is_string($code) && array_key_exists($code, self::BUILT_IN_TYPES);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class, 'transaction_type');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
