<?php

use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\FranchiseRenewal;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::create(['name' => 'Staff', 'username' => 'transaction-staff', 'password' => 'password']);
    $this->user->givePermissionTo(Permission::create(['name' => 'view franchise', 'guard_name' => 'web']));
    $this->franchise = Franchise::create(['franchise_number' => 'F-001', 'expiration_date' => '2026-11-17']);
    $this->owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $this->franchise->ownerships()->create(['franchise_owner_id' => $this->owner->id, 'started_at' => today()->subYear()]);
    $this->actingAs($this->user);
});

function renewalDetails($ownerId): array
{
    return ['franchise_owner_id' => $ownerId, 'renewal_date' => '2026-11-17', 'previous_expiration_date' => '2026-11-17', 'new_expiration_date' => '2027-11-17'];
}

test('opening transaction options does not create a transaction', function () {
    $this->getJson(route('franchise.transactions.options', $this->franchise))->assertOk()->assertJsonPath('current_owner_ids.0', $this->owner->id);
    $this->assertDatabaseCount('franchise_transactions', 0);
});

test('incomplete transaction details never create a parent record', function (string $type) {
    $this->postJson(route('franchise.transactions.store', $this->franchise), ['transaction_type' => $type, 'date_filed' => '2026-11-17'])
        ->assertUnprocessable()->assertJsonValidationErrors('details');
    $this->assertDatabaseCount('franchise_transactions', 0);
})->with(['renewal', 'amendment', 'substitution', 'application']);

test('renewal saves with its details without updating the franchise', function () {
    $this->postJson(route('franchise.transactions.store', $this->franchise), ['transaction_type' => 'renewal', 'date_filed' => '2026-11-17', 'details' => renewalDetails($this->owner->id)])->assertCreated();
    $transaction = $this->franchise->transactions()->sole();
    expect($transaction->renewal->franchise_owner_id)->toBe($this->owner->id)->and($transaction->status)->toBe('pending');
    expect($this->franchise->fresh()->expiration_date->format('Y-m-d'))->toBe('2026-11-17');
});

test('amendment saves proposed changes and server-derived previous values', function () {
    $this->postJson(route('franchise.transactions.store', $this->franchise), ['transaction_type' => 'amendment', 'date_filed' => '2026-11-17', 'details' => [
        'amendment_type' => 'change_details', 'reason' => 'Correction', 'changes' => [['field_name' => 'franchise_number', 'old_value' => 'forged', 'new_value' => 'F-002']],
    ]])->assertCreated();
    $change = $this->franchise->transactions()->sole()->amendment->changes()->sole();
    expect($change->old_value)->toBe('F-001')->and($change->new_value)->toBe('F-002');
    expect($this->franchise->fresh()->franchise_number)->toBe('F-001');
});

test('transfer saves amendment and transfer details without changing ownership', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ben', 'last_name' => 'Reyes']);
    $this->postJson(route('franchise.transactions.store', $this->franchise), ['transaction_type' => 'amendment', 'date_filed' => '2026-11-17', 'details' => [
        'amendment_type' => 'transfer_ownership', 'reason' => 'Sale', 'previous_owner_id' => $this->owner->id, 'new_owner_id' => $owner->id, 'transfer_date' => '2026-11-17',
    ]])->assertCreated();
    expect($this->franchise->transactions()->sole()->amendment->transfer->new_owner_id)->toBe($owner->id);
    expect($this->franchise->ownerships()->count())->toBe(1);
});

test('substitution requires different vehicles and saves details', function () {
    $previous = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'ABC123']);
    $next = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'ABC456']);
    $data = ['transaction_type' => 'substitution', 'date_filed' => '2026-11-17', 'details' => ['previous_vehicle_id' => $previous->id, 'new_vehicle_id' => $previous->id, 'substitution_date' => '2026-11-17', 'reason' => 'Replacement']];
    $this->postJson(route('franchise.transactions.store', $this->franchise), $data)->assertUnprocessable()->assertJsonValidationErrors('details.new_vehicle_id');
    $this->assertDatabaseCount('franchise_transactions', 0);
    $data['details']['new_vehicle_id'] = $next->id;
    $this->postJson(route('franchise.transactions.store', $this->franchise), $data)->assertCreated();
    expect($this->franchise->transactions()->sole()->substitution->new_vehicle_id)->toBe($next->id);
});

test('a detail persistence failure rolls back the parent transaction', function () {
    FranchiseRenewal::creating(fn () => throw new RuntimeException('Simulated detail failure'));
    try {
        $this->postJson(route('franchise.transactions.store', $this->franchise), ['transaction_type' => 'renewal', 'date_filed' => '2026-11-17', 'details' => renewalDetails($this->owner->id)])->assertStatus(500);
        $this->assertDatabaseCount('franchise_transactions', 0);
    } finally {
        FranchiseRenewal::flushEventListeners();
    }
});

test('renewal owner must belong to the selected franchise', function () {
    $other = FranchiseOwner::create(['first_name' => 'Other', 'last_name' => 'Owner']);
    $this->postJson(route('franchise.transactions.store', $this->franchise), ['transaction_type' => 'renewal', 'date_filed' => '2026-11-17', 'details' => renewalDetails($other->id)])->assertUnprocessable()->assertJsonValidationErrors('details.franchise_owner_id');
    $this->assertDatabaseCount('franchise_transactions', 0);
});

test('filing timestamp is automatic and client filing date and remarks are ignored', function () {
    $this->travelTo(now()->setDate(2026, 11, 17)->setTime(10, 30, 45));
    $this->postJson(route('franchise.transactions.store', $this->franchise), [
        'transaction_type' => 'application', 'details' => ['confirmed' => true],
        'date_filed' => '2000-01-01', 'created_at' => '2000-01-01', 'remarks' => 'Ignored',
    ])->assertCreated();
    $transaction = $this->franchise->transactions()->sole();
    expect($transaction->created_at->format('Y-m-d H:i:s'))->toBe('2026-11-17 10:30:45')
        ->and($transaction->date_filed->format('Y-m-d'))->toBe('2026-11-17')
        ->and($transaction->remarks)->toBeNull();
});
