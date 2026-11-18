<?php

use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::create(['name' => 'Transaction Staff', 'username' => 'tx-page', 'password' => 'password']);
    $this->staff->givePermissionTo(Permission::create(['name' => 'view transactions', 'guard_name' => 'web']));
});

test('standalone transaction page lists and filters franchise transactions', function () {
    $franchise = Franchise::create(['franchise_number' => 'SC-0001']);
    foreach (range(1, 12) as $index) {
        $franchise->transactions()->create(['transaction_type' => 'renewal', 'date_filed' => today()]);
    }
    $other = Franchise::create(['franchise_number' => 'SC-0002']);
    $other->transactions()->create(['transaction_type' => 'application', 'status' => 'approved', 'date_filed' => today()]);
    $this->actingAs($this->staff)->get(route('transactions.index'))->assertInertia(fn (Assert $page) => $page->component('Admin/Transactions/Index')->has('transactions.data', 10)->where('transactions.total', 13));
    $this->getJson(route('transactions.index', ['search' => 'SC-0001', 'status' => 'pending', 'page' => 2]))->assertOk()->assertJsonPath('transactions.total', 12)->assertJsonCount(2, 'transactions.data');
    $this->getJson(route('transactions.index', ['status' => 'approved']))->assertOk()->assertJsonPath('transactions.total', 1)->assertJsonPath('transactions.data.0.franchise.franchise_number', 'SC-0002');
});

test('searching franchise dropdown and opening options never saves a transaction', function () {
    foreach (range(1, 25) as $index) {
        Franchise::create(['franchise_number' => sprintf('SC-%04d', $index)]);
    }
    $franchise = Franchise::where('franchise_number', 'SC-0023')->firstOrFail();
    $this->actingAs($this->staff)->getJson(route('transactions.franchises'))->assertOk()->assertJsonCount(20, 'franchises');
    $this->getJson(route('transactions.franchises', ['search' => '0023']))->assertOk()->assertJsonCount(1, 'franchises')->assertJsonPath('franchises.0.id', $franchise->id);
    $this->getJson(route('transactions.franchises', ['id' => $franchise->id]))->assertOk()->assertJsonPath('franchises.0.franchise_number', 'SC-0023');
    $this->getJson(route('transactions.options', $franchise))->assertOk();
    $this->assertDatabaseCount('franchise_transactions', 0);
});

test('final standalone submission validates the franchise and all detail fields', function () {
    $franchise = Franchise::create(['franchise_number' => 'SC-0001']);
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $franchise->ownerships()->create(['franchise_owner_id' => $owner->id, 'started_at' => today()->subYear()]);
    $data = ['transaction_type' => 'renewal', 'franchise_id' => $franchise->id];
    $this->actingAs($this->staff)->postJson(route('transactions.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('details');
    $this->assertDatabaseCount('franchise_transactions', 0);
    $vehicle = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'PAGE-001']);
    $franchise->vehicleAssignments()->create(['vehicle_id' => $vehicle->id, 'started_at' => today()->subYear()]);
    $data['vehicle'] = ['mode' => 'existing', 'id' => $vehicle->id];
    $data['details'] = ['franchise_owner_id' => $owner->id, 'renewal_date' => '2026-11-17', 'previous_expiration_date' => '2026-11-17', 'new_expiration_date' => '2027-11-17'];
    $this->postJson(route('transactions.store'), [...$data, 'franchise_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('franchise_id');
    $this->postJson(route('transactions.store'), $data)->assertCreated();
    $transaction = $franchise->transactions()->sole();
    expect($transaction->status)->toBe('pending')->and($transaction->renewal->franchise_owner_id)->toBe($owner->id);
});

test('authorized staff can review and approve a renewal once', function () {
    $franchise = Franchise::create(['franchise_number' => 'SC-0001', 'expiration_date' => '2026-11-17']);
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $transaction = $franchise->transactions()->create([
        'transaction_type' => 'renewal', 'date_filed' => today(), 'certificate_issue_date' => '2026-11-18',
    ]);
    $transaction->renewal()->create([
        'franchise_owner_id' => $owner->id, 'renewal_date' => '2026-11-18',
        'previous_expiration_date' => '2026-11-17', 'new_expiration_date' => '2027-11-17',
    ]);
    $url = route('transactions.status.update', $transaction);

    $this->actingAs($this->staff)->patchJson($url, ['status' => 'approved'])->assertForbidden();
    $this->staff->givePermissionTo(Permission::create(['name' => 'process transactions', 'guard_name' => 'web']));
    $this->patchJson($url, ['status' => 'under_review'])->assertOk();
    expect($franchise->fresh()->date_issued)->toBeNull();
    $this->patchJson($url, ['status' => 'approved'])->assertOk();
    $this->patchJson($url, ['status' => 'cancelled'])->assertUnprocessable()->assertJsonValidationErrors('status');

    $transaction->refresh();
    expect($transaction->status)->toBe('approved')
        ->and($transaction->processed_by)->toBe($this->staff->id)
        ->and($transaction->approved_at)->not->toBeNull();
    $franchise->refresh();
    expect($franchise->date_issued->toDateString())->toBe('2026-11-18')
        ->and($franchise->expiration_date->toDateString())->toBe('2027-11-17');
});

test('all standalone transaction endpoints require transaction page permission', function () {
    $franchise = Franchise::create(['franchise_number' => 'SC-0001']);
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
    $this->staff->revokePermissionTo('view transactions');
    $this->staff->givePermissionTo(Permission::create(['name' => 'view franchise', 'guard_name' => 'web']));
    $this->actingAs($this->staff)->getJson(route('transactions.index'))->assertForbidden();
    $this->getJson(route('transactions.franchises'))->assertForbidden();
    $this->getJson(route('transactions.options', $franchise))->assertForbidden();
    $this->postJson(route('transactions.store'), [])->assertForbidden();
});
