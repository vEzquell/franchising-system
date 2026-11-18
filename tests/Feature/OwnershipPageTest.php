<?php

use App\Models\Attachment;
use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->staff = User::create(['name' => 'Staff', 'username' => 'ownership-staff', 'password' => 'password']);
    $this->staff->givePermissionTo(Permission::create(['name' => 'view ownerships', 'guard_name' => 'web']));
    $this->franchise = Franchise::create(['franchise_number' => 'F-HISTORY']);
    $this->owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $this->ownership = $this->owner->ownerships()->create(['franchise_id' => $this->franchise->id, 'started_at' => today()->subYear()]);
});

test('ownership list is searchable paginated and includes historical records', function () {
    foreach (range(1, 12) as $index) {
        $this->owner->ownerships()->create(['franchise_id' => $this->franchise->id, 'started_at' => today()->subYears($index + 1), 'ended_at' => today()->subYears($index)]);
    }
    $this->actingAs($this->staff)->get(route('ownerships.index'))->assertInertia(fn (Assert $page) => $page->component('Admin/Ownership/Index')->has('ownerships.data', 10)->where('ownerships.total', 13));
    $this->getJson(route('ownerships.index', ['search' => 'Ana Cruz', 'period' => 'previous', 'page' => 2]))->assertOk()->assertJsonPath('ownerships.total', 12)->assertJsonCount(2, 'ownerships.data');
    $this->getJson(route('ownerships.index', ['search' => 'F-HISTORY', 'period' => 'current']))->assertOk()->assertJsonPath('ownerships.total', 1);
});

test('documents stay with their ownership after renewal or transfer and allow historical uploads', function () {
    $upload = fn () => ['attachments' => [['document_name' => 'Test document', 'document_type' => 'Franchise certificate', 'file' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf')]]];
    $this->actingAs($this->staff)->postJson(route('ownerships.attachments.store', $this->ownership), $upload())->assertCreated();
    $this->ownership->update(['ended_at' => today()]);
    $newOwner = FranchiseOwner::create(['first_name' => 'Ben', 'last_name' => 'Reyes']);
    $next = $newOwner->ownerships()->create(['franchise_id' => $this->franchise->id, 'started_at' => today()]);
    $this->franchise->update(['expiration_date' => today()->addYear()]);
    $this->postJson(route('ownerships.attachments.store', $this->ownership), $upload())->assertCreated();
    expect($this->ownership->attachments()->count())->toBe(2)->and($next->attachments()->count())->toBe(0);
    $attachment = $this->ownership->attachments()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);
    $this->getJson(route('ownerships.show', $this->ownership))->assertOk()->assertJsonCount(2, 'ownership.attachments')
        ->assertJsonPath('ownership.attachments.0.id', $this->ownership->attachments()->max('id'))
        ->assertJsonPath('ownership.attachments.1.id', $attachment->id)
        ->assertJsonMissingPath('ownership.attachments.0.path');
    $this->get(route('ownerships.attachments.download', [$this->ownership, $attachment]))->assertDownload('certificate.pdf');
    $this->get(route('ownerships.attachments.download', [$next, $attachment]))->assertNotFound();
});

test('ownership documents require page permission and valid uploads', function () {
    $this->get(route('ownerships.index'))->assertRedirect(route('login'));
    $other = User::create(['name' => 'Other', 'username' => 'no-ownership', 'password' => 'password']);
    $this->actingAs($other)->getJson(route('ownerships.index'))->assertForbidden();
    $this->getJson(route('ownerships.show', $this->ownership))->assertForbidden();
    $this->postJson(route('ownerships.attachments.store', $this->ownership), [])->assertForbidden();
    $this->actingAs($this->staff)->postJson(route('ownerships.attachments.store', $this->ownership), ['attachments' => [['document_name' => 'Test document', 'document_type' => 'Invalid', 'file' => UploadedFile::fake()->create('bad.exe', 10, 'application/octet-stream')]]])->assertUnprocessable()->assertJsonValidationErrors('attachments.0.file');
    $this->assertDatabaseCount('attachments', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('failed ownership document save cleans up files', function () {
    Attachment::creating(fn () => throw new RuntimeException('Simulated save failure'));
    try {
        $this->actingAs($this->staff)->postJson(route('ownerships.attachments.store', $this->ownership), ['attachments' => [['document_name' => 'Test document', 'document_type' => 'Certificate', 'file' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf')]]])->assertStatus(500);
        $this->assertDatabaseCount('attachments', 0);
        expect(Storage::disk('local')->allFiles())->toBe([]);
    } finally {
        Attachment::flushEventListeners();
    }
});

test('ownership documents require a descriptive name and save it separately from the source filename', function () {
    $data = ['attachments' => [['file' => UploadedFile::fake()->create('scan-page.pdf', 20, 'application/pdf')]]];
    $this->actingAs($this->staff)->postJson(route('ownerships.attachments.store', $this->ownership), $data)->assertUnprocessable()->assertJsonValidationErrors('attachments.0.document_name');
    $data['attachments'][0]['document_name'] = '2026 franchise certificate';
    $this->postJson(route('ownerships.attachments.store', $this->ownership), $data)->assertCreated();
    $attachment = $this->ownership->attachments()->sole();
    expect($attachment->document_name)->toBe('2026 franchise certificate')
        ->and($attachment->document_type)->toBe('PDF')
        ->and($attachment->original_name)->toBe('scan-page.pdf');
});
