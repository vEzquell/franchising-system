<?php

use App\Models\Attachment;
use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->staff = User::create(['name' => 'Staff', 'username' => 'vehicle-staff', 'password' => 'password']);
    $this->staff->givePermissionTo(Permission::create(['name' => 'view transactions', 'guard_name' => 'web']));
    $this->staff->givePermissionTo(Permission::create(['name' => 'view franchise', 'guard_name' => 'web']));
    $this->franchise = Franchise::create(['franchise_number' => 'F-VEHICLE']);
    $this->owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $this->actingAs($this->staff);
});

function vehicleApplication($test): array
{
    return [
        'franchise_id' => $test->franchise->id, 'transaction_type' => 'application',
        'details' => ['confirmed' => true, 'franchise_owner_id' => $test->owner->id],
        'vehicle' => ['mode' => 'new', 'make_type' => 'Motorcycle', 'plate_number' => 'ABC-001'],
        'attachments' => [['document_name' => 'Test document', 'document_type' => 'Registration', 'file' => UploadedFile::fake()->create('registration.pdf', 5120, 'application/pdf')]],
    ];
}

test('application stores vehicle snapshot and private attachments only on final submission', function () {
    $this->franchise->ownerships()->create(['franchise_owner_id' => $this->owner->id, 'started_at' => today()->subYears(2), 'ended_at' => today()->subYear()]);
    $ownership = $this->franchise->ownerships()->create(['franchise_owner_id' => $this->owner->id, 'started_at' => today()->subYear()]);
    $this->getJson(route('transactions.options', $this->franchise))->assertOk();
    $this->assertDatabaseCount('vehicles', 0);
    $this->assertDatabaseCount('franchise_transactions', 0);
    $this->postJson(route('transactions.store'), vehicleApplication($this))->assertCreated();
    $transaction = $this->franchise->transactions()->sole();
    expect($transaction->status)->toBe('pending')->and($transaction->vehicleDetails->vehicle_details['plate_number'])->toBe('ABC-001')
        ->and($transaction->vehicleDetails->franchise_owner_id)->toBe($this->owner->id);
    $attachment = $transaction->attachments()->sole();
    expect($attachment->franchise_ownership_id)->toBe($ownership->id);
    Storage::disk('local')->assertExists($attachment->path);
    $this->assertDatabaseCount('franchise_vehicle_assignments', 0);
    $this->getJson(route('transactions.show', $transaction))->assertOk()->assertJsonMissingPath('transaction.attachments.0.path');
    $this->get(route('transactions.attachments.download', [$transaction, $attachment]))->assertOk()->assertDownload('registration.pdf');
    $ownershipOnly = User::create(['name' => 'Ownership Staff', 'username' => 'ownership-staff', 'password' => 'password']);
    $ownershipOnly->givePermissionTo(Permission::create(['name' => 'view ownerships', 'guard_name' => 'web']));
    $this->actingAs($ownershipOnly)->getJson(route('ownerships.show', $ownership))->assertOk()->assertJsonCount(0, 'ownership.attachments');
    $this->get(route('ownerships.attachments.download', [$ownership, $attachment]))->assertForbidden();
    $ownershipOnly->givePermissionTo('view transactions');
    $this->getJson(route('ownerships.show', $ownership))->assertOk()->assertJsonPath('ownership.attachments.0.id', $attachment->id);
    $this->get(route('ownerships.attachments.download', [$ownership, $attachment]))->assertDownload('registration.pdf');
});

test('initial vehicle assignment is dated and prevents overlapping assignments', function () {
    $vehicle = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'BASE-001']);
    $data = ['vehicle' => ['mode' => 'existing', 'id' => $vehicle->id], 'started_at' => today()->toDateString()];
    $this->postJson(route('franchise.vehicles.store', $this->franchise), $data)->assertCreated();
    $this->getJson(route('transactions.options', $this->franchise))->assertOk()->assertJsonPath('current_vehicles.0.id', $vehicle->id);
    $other = Franchise::create(['franchise_number' => 'F-OTHER']);
    $this->postJson(route('franchise.vehicles.store', $other), $data)->assertUnprocessable()->assertJsonValidationErrors('vehicle.id');
    $this->postJson(route('franchise.vehicles.store', $this->franchise), ['vehicle' => ['mode' => 'new', 'make_type' => 'Motorcycle', 'plate_number' => 'ROLLBACK-NEW'], 'started_at' => today()->toDateString()])->assertUnprocessable();
    $this->assertDatabaseCount('vehicles', 1);
    $this->assertDatabaseCount('franchise_vehicle_assignments', 1);
});

test('new vehicles can share identifiers when any vehicle detail differs', function (array $changedDetails) {
    $original = ['make_type' => 'Motorcycle', 'plate_number' => 'SHARED-001', 'motor_number' => 'M-001', 'chassis_number' => 'C-001'];
    Vehicle::create($original);
    $details = array_replace($original, $changedDetails);

    $this->postJson(route('franchise.vehicles.store', $this->franchise), [
        'vehicle' => ['mode' => 'new', ...$details],
        'started_at' => today()->toDateString(),
    ])->assertCreated();

    $this->assertDatabaseHas('vehicles', $details);
})->with([
    'different make or type' => [['make_type' => 'Tricycle']],
    'different plate' => [['plate_number' => 'SHARED-002']],
    'different motor' => [['motor_number' => 'M-002']],
    'different chassis' => [['chassis_number' => 'C-002']],
    'both identifiers changed' => [['motor_number' => 'M-002', 'chassis_number' => 'C-002']],
]);

test('identical vehicle details are rejected even when optional numbers are missing', function (array $identifiers) {
    $details = ['make_type' => 'Motorcycle', ...$identifiers];
    Vehicle::create($details);

    $this->postJson(route('franchise.vehicles.store', $this->franchise), [
        'vehicle' => ['mode' => 'new', ...$details],
        'started_at' => today()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vehicle.plate_number' => 'A vehicle with these same details already exists.']);

    $this->assertDatabaseCount('vehicles', 1);
    $this->assertDatabaseCount('franchise_vehicle_assignments', 0);
    expect(fn () => Vehicle::create($details))
        ->toThrow(UniqueConstraintViolationException::class);
})->with([
    'all identifiers' => [['plate_number' => 'SHARED-001', 'motor_number' => 'M-001', 'chassis_number' => 'C-001']],
    'plate only' => [['plate_number' => 'SHARED-001']],
    'chassis only' => [['chassis_number' => 'C-001']],
]);

test('vehicle assignment options return details for the current link only', function () {
    $vehicle = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'CUR-001', 'motor_number' => 'M-001', 'chassis_number' => 'C-001']);
    $oldVehicle = Vehicle::create(['make_type' => 'Old motorcycle', 'plate_number' => 'OLD-001']);
    $futureVehicle = Vehicle::create(['make_type' => 'Future motorcycle', 'plate_number' => 'FUT-001']);
    $url = route('franchise.vehicles', $this->franchise);
    $today = today();
    $this->franchise->vehicleAssignments()->create(['vehicle_id' => $oldVehicle->id, 'started_at' => $today->copy()->subYear(), 'ended_at' => $today]);
    $this->franchise->vehicleAssignments()->create(['vehicle_id' => $futureVehicle->id, 'started_at' => $today->copy()->addWeek()]);

    $this->getJson($url)->assertOk()->assertJsonPath('current_assignment', null);

    $assignment = $this->franchise->vehicleAssignments()->create(['vehicle_id' => $vehicle->id, 'started_at' => $today, 'ended_at' => $today->copy()->addWeek()]);

    $this->getJson($url)->assertOk()->assertJsonCount(3, 'vehicles')
        ->assertJsonPath('current_assignment.id', $assignment->id)
        ->assertJsonPath('current_assignment.started_at', $today->toJSON())
        ->assertJsonPath('current_assignment.vehicle.id', $vehicle->id)
        ->assertJsonPath('current_assignment.vehicle.make_type', 'Motorcycle')
        ->assertJsonPath('current_assignment.vehicle.plate_number', 'CUR-001')
        ->assertJsonPath('current_assignment.vehicle.motor_number', 'M-001')
        ->assertJsonPath('current_assignment.vehicle.chassis_number', 'C-001');
});

test('renewal snapshots requested details without modifying the assigned vehicle', function () {
    $vehicle = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'OLD-001']);
    $this->franchise->vehicleAssignments()->create(['vehicle_id' => $vehicle->id, 'started_at' => today()->subYear()]);
    $this->franchise->ownerships()->create(['franchise_owner_id' => $this->owner->id, 'started_at' => today()->subYear()]);
    $data = ['franchise_id' => $this->franchise->id, 'transaction_type' => 'renewal', 'vehicle' => ['mode' => 'existing', 'id' => $vehicle->id, 'make_type' => 'Updated motorcycle', 'plate_number' => 'NEW-001'], 'details' => [
        'franchise_owner_id' => $this->owner->id, 'renewal_date' => '2026-11-17', 'previous_expiration_date' => '2026-11-17', 'new_expiration_date' => '2027-11-17',
    ]];
    $this->postJson(route('transactions.store'), $data)->assertCreated();
    expect($this->franchise->transactions()->sole()->vehicleDetails->vehicle_details['plate_number'])->toBe('NEW-001');
    expect($vehicle->fresh()->plate_number)->toBe('OLD-001');
    $other = Vehicle::create(['make_type' => 'Other']);
    $data['vehicle']['id'] = $other->id;
    $this->postJson(route('transactions.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('vehicle.id');
});

test('substitution can register a replacement without changing active assignments', function () {
    $vehicle = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'PREV-001']);
    $this->franchise->vehicleAssignments()->create(['vehicle_id' => $vehicle->id, 'started_at' => today()->subYear()]);
    $data = vehicleApplication($this);
    $data['transaction_type'] = 'substitution';
    $data['certificate_issue_date'] = '2026-11-18';
    $data['details'] = ['previous_vehicle_id' => $vehicle->id, 'substitution_date' => '2026-11-17', 'reason' => 'Replacement'];
    $this->postJson(route('transactions.store'), $data)->assertCreated();
    $transaction = $this->franchise->transactions()->sole();
    $this->getJson(route('transactions.show', $transaction))->assertOk()->assertJsonPath('transaction.certificate_issue_date', '2026-11-18');
    expect($transaction->substitution->new_vehicle_id)->toBe($transaction->vehicleDetails->vehicle_id);
    expect($this->franchise->vehicleAssignments()->sole()->vehicle_id)->toBe($vehicle->id);

    auth()->user()->givePermissionTo(Permission::create(['name' => 'process transactions', 'guard_name' => 'web']));
    $this->patchJson(route('transactions.status.update', $transaction), ['status' => 'approved'])->assertOk();
    expect($this->franchise->fresh()->date_issued->toDateString())->toBe('2026-11-18');
    expect($this->franchise->vehicleAssignments()->where('vehicle_id', $vehicle->id)->sole()->ended_at->toDateString())->toBe('2026-11-17');
    expect($this->franchise->vehicleAssignments()->whereNull('ended_at')->sole()->vehicle_id)->toBe($transaction->substitution->new_vehicle_id);
});

test('ownership transfer can replace the vehicle in the same approval', function () {
    $date = today()->toDateString();
    $incoming = FranchiseOwner::create(['first_name' => 'Ben', 'last_name' => 'Reyes']);
    $previous = Vehicle::create(['make_type' => 'Motorcycle', 'plate_number' => 'PREV-001']);
    $this->franchise->ownerships()->create(['franchise_owner_id' => $this->owner->id, 'started_at' => today()->subYear()]);
    $this->franchise->vehicleAssignments()->create(['vehicle_id' => $previous->id, 'started_at' => today()->subYear()]);
    $data = vehicleApplication($this);
    $data['transaction_type'] = 'amendment';
    $data['details'] = [
        'amendment_type' => 'transfer_ownership',
        'reason' => 'Sale with vehicle replacement',
        'previous_owner_id' => $this->owner->id,
        'new_owner_id' => $incoming->id,
        'transfer_date' => $date,
        'change_vehicle' => true,
        'previous_vehicle_id' => $previous->id,
    ];

    $this->postJson(route('transactions.store'), array_diff_key($data, ['vehicle' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('vehicle');
    $this->postJson(route('transactions.store'), $data)->assertCreated();

    $transaction = $this->franchise->transactions()->sole();
    expect($transaction->status)->toBe('pending')
        ->and($this->franchise->ownerships()->sole()->franchise_owner_id)->toBe($this->owner->id)
        ->and($this->franchise->vehicleAssignments()->sole()->vehicle_id)->toBe($previous->id);
    $attachment = $transaction->attachments()->sole();
    expect($attachment->franchise_ownership_id)->toBeNull();

    $this->staff->givePermissionTo(Permission::create(['name' => 'process transactions', 'guard_name' => 'web']));
    $this->patchJson(route('transactions.status.update', $transaction), ['status' => 'approved'])->assertOk();
    $ownership = $this->franchise->ownerships()->whereNull('ended_at')->sole();
    expect($ownership->franchise_owner_id)->toBe($incoming->id)
        ->and($attachment->fresh()->franchise_ownership_id)->toBe($ownership->id)
        ->and($attachment->fresh()->franchise_transaction_id)->toBe($transaction->id)
        ->and($this->franchise->vehicleAssignments()->whereNull('ended_at')->sole()->vehicle_id)->toBe($transaction->substitution->new_vehicle_id)
        ->and($this->franchise->fresh()->date_issued->toDateString())->toBe($date);

    $previousOwnership = $this->franchise->ownerships()->whereNotNull('ended_at')->sole();
    $attachment->update(['franchise_ownership_id' => $previousOwnership->id]);
    $migration = require database_path('migrations/2026_11_18_030000_correct_transfer_attachment_ownership.php');
    $migration->up();
    expect($attachment->fresh()->franchise_ownership_id)->toBe($ownership->id);
});

test('invalid uploads and incomplete vehicles create no records or files', function () {
    $data = vehicleApplication($this);
    $data['certificate_issue_date'] = '2026-02-30';
    $this->postJson(route('transactions.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('certificate_issue_date');
    unset($data['certificate_issue_date']);
    $data['attachments'][0]['file'] = UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream');
    $this->postJson(route('transactions.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('attachments.0.file');
    $data['attachments'][0]['file'] = UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf');
    $this->postJson(route('transactions.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('attachments.0.file');
    unset($data['vehicle']['plate_number']);
    $this->postJson(route('transactions.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('vehicle.plate_number');
    $this->assertDatabaseCount('franchise_transactions', 0);
    $this->assertDatabaseCount('vehicles', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('attachment metadata failure rolls back records and removes stored files', function () {
    Attachment::creating(fn () => throw new RuntimeException('Simulated attachment failure'));
    try {
        $this->postJson(route('transactions.store'), vehicleApplication($this))->assertStatus(500);
        $this->assertDatabaseCount('franchise_transactions', 0);
        $this->assertDatabaseCount('vehicles', 0);
        expect(Storage::disk('local')->allFiles())->toBe([]);
    } finally {
        Attachment::flushEventListeners();
    }
});

test('attachments cannot be downloaded through another transaction or without permission', function () {
    $this->postJson(route('transactions.store'), vehicleApplication($this))->assertCreated();
    $transaction = $this->franchise->transactions()->sole();
    $attachment = $transaction->attachments()->sole();
    $other = $this->franchise->transactions()->create(['transaction_type' => 'application', 'date_filed' => today()]);
    $this->get(route('transactions.attachments.download', [$other, $attachment]))->assertNotFound();
    $user = User::create(['name' => 'Other', 'username' => 'no-files', 'password' => 'password']);
    $this->actingAs($user)->get(route('transactions.attachments.download', [$transaction, $attachment]))->assertForbidden();
    $this->getJson(route('transactions.show', $transaction))->assertForbidden();
    $this->postJson(route('franchise.vehicles.store', $this->franchise), [])->assertForbidden();
});

test('combined upload size is validated before any persistence', function () {
    $data = vehicleApplication($this);
    $data['attachments'] = array_map(fn ($index) => ['document_name' => 'Test document', 'document_type' => 'Supporting document', 'file' => UploadedFile::fake()->create('file'.$index.'.pdf', 2000, 'application/pdf')], range(1, 4));
    $this->postJson(route('transactions.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('attachments');
    $this->assertDatabaseCount('franchise_transactions', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
