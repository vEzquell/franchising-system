<?php

use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\FranchiseTransaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('franchise history persists through the model relationships and date casts', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-001', 'date_issued' => '2026-01-01']);
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $nextOwner = FranchiseOwner::create(['first_name' => 'Ben', 'last_name' => 'Reyes']);
    $ownership = $owner->ownerships()->create(['franchise_id' => $franchise->id, 'started_at' => '2026-01-01']);
    expect($ownership->franchise->is($franchise))->toBeTrue()
        ->and($ownership->owner->is($owner))->toBeTrue()
        ->and($ownership->started_at)->toBeInstanceOf(Carbon::class)
        ->and($franchise->ownerships)->toHaveCount(1);

    $processor = User::create(['name' => 'Processor', 'username' => 'processor', 'password' => 'password']);
    $transaction = $franchise->transactions()->create([
        'transaction_type' => 'renewal', 'date_filed' => '2026-11-17',
        'approved_at' => '2026-11-17 09:30:00', 'processed_by' => $processor->id,
    ]);
    $renewal = $transaction->renewal()->create([
        'franchise_owner_id' => $owner->id,
        'renewal_date' => '2026-11-17', 'previous_expiration_date' => '2026-11-17', 'new_expiration_date' => '2027-11-17',
    ]);
    expect($renewal->transaction->is($transaction))->toBeTrue()
        ->and($renewal->franchise->is($franchise))->toBeTrue()
        ->and($renewal->owner->is($owner))->toBeTrue()
        ->and($renewal->new_expiration_date)->toBeInstanceOf(Carbon::class)
        ->and($franchise->renewals)->toHaveCount(1)
        ->and($owner->renewals)->toHaveCount(1)
        ->and($transaction->approved_at->format('H:i:s'))->toBe('09:30:00')
        ->and($transaction->processor->is($processor))->toBeTrue();
    expect(Franchise::with('renewals')->findOrFail($franchise->id)->renewals->first()->is($renewal))->toBeTrue();
    expect($renewal->fresh()->load('franchise')->franchise->is($franchise))->toBeTrue();
    $processor->delete();
    expect($transaction->fresh()->processed_by)->toBeNull();

    $amendmentTransaction = $franchise->transactions()->create(['transaction_type' => 'amendment', 'date_filed' => '2026-11-17']);
    $amendment = $amendmentTransaction->amendment()->create(['amendment_type' => 'transfer_ownership']);
    $change = $amendment->changes()->create(['field_name' => 'franchise_number', 'old_value' => 'F-001', 'new_value' => 'F-002']);
    $transfer = $amendment->transfer()->create(['previous_owner_id' => $owner->id, 'new_owner_id' => $nextOwner->id, 'transfer_date' => '2026-11-17']);
    expect($amendment->transaction->is($amendmentTransaction))->toBeTrue()
        ->and($change->amendment->is($amendment))->toBeTrue()
        ->and($transfer->amendment->is($amendment))->toBeTrue()
        ->and($transfer->previousOwner->is($owner))->toBeTrue()
        ->and($transfer->newOwner->is($nextOwner))->toBeTrue()
        ->and($owner->outgoingTransfers)->toHaveCount(1)
        ->and($nextOwner->incomingTransfers)->toHaveCount(1);

    $previousVehicle = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'ABC123']);
    $newVehicle = Vehicle::create(['make_type' => 'Motorcycle', 'chassis_number' => 'CHASSIS-002']);
    $substitutionTransaction = $franchise->transactions()->create(['transaction_type' => 'substitution', 'date_filed' => '2026-11-17']);
    $substitution = $substitutionTransaction->substitution()->create(['previous_vehicle_id' => $previousVehicle->id, 'new_vehicle_id' => $newVehicle->id]);
    expect($substitution->transaction->is($substitutionTransaction))->toBeTrue()
        ->and($substitution->previousVehicle->is($previousVehicle))->toBeTrue()
        ->and($substitution->newVehicle->is($newVehicle))->toBeTrue()
        ->and($previousVehicle->outgoingSubstitutions)->toHaveCount(1)
        ->and($newVehicle->incomingSubstitutions)->toHaveCount(1)
        ->and($franchise->transactions)->toHaveCount(3);
});

test('franchise audit records restrict deletion and reject missing parents', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-001']);
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $franchise->ownerships()->create(['franchise_owner_id' => $owner->id, 'started_at' => '2026-01-01']);
    expect(fn () => $owner->delete())->toThrow(QueryException::class);
    expect(fn () => $franchise->delete())->toThrow(QueryException::class);
    expect(fn () => FranchiseTransaction::create(['franchise_id' => 99999, 'transaction_type' => 'application', 'date_filed' => '2026-11-17']))->toThrow(QueryException::class);
});

test('transaction details and amendment fields cannot be duplicated', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-001']);
    $transaction = $franchise->transactions()->create(['transaction_type' => 'amendment', 'date_filed' => '2026-11-17']);
    $amendment = $transaction->amendment()->create(['amendment_type' => 'change_details']);
    expect(fn () => $transaction->amendment()->create(['amendment_type' => 'change_details']))->toThrow(QueryException::class);
    $amendment->changes()->create(['field_name' => 'franchise_number']);
    expect(fn () => $amendment->changes()->create(['field_name' => 'franchise_number']))->toThrow(QueryException::class);
});

test('franchise migrations can roll back in reverse dependency order and run again', function () {
    $paths = glob(database_path('migrations/2026_11_17_*.php'));
    sort($paths);
    foreach (array_reverse($paths) as $path) {
        $migration = require $path;
        $migration->down();
    }
    expect(Schema::hasTable('franchise_amendment_changes'))->toBeFalse()
        ->and(Schema::hasTable('vehicles'))->toBeFalse()
        ->and(Schema::hasTable('franchises'))->toBeFalse();
    foreach ($paths as $path) {
        $migration = require $path;
        $migration->up();
    }
    expect(Schema::hasColumn('franchise_transactions', 'approved_at'))->toBeTrue();
});
