<?php

use App\Models\Attachment;
use App\Models\Franchise;
use App\Models\FranchiseOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('attachment consolidation preserves both legacy sources with overlapping ids and supports rollback', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-MERGE']);
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $ownership = $owner->ownerships()->create(['franchise_id' => $franchise->id, 'started_at' => today()]);
    $transaction = $franchise->transactions()->create(['transaction_type' => 'application', 'date_filed' => today()]);
    $migration = require database_path('migrations/2026_11_17_070000_unify_attachments_tables.php');
    $migration->down();
    $metadata = ['id' => 1, 'document_type' => 'Certificate', 'original_name' => 'certificate.pdf', 'disk' => 'local', 'mime_type' => 'application/pdf', 'size' => 100, 'created_at' => now(), 'updated_at' => now()];
    DB::table('transaction_attachments')->insert([...$metadata, 'franchise_transaction_id' => $transaction->id, 'path' => 'transaction-attachments/legacy.pdf']);
    DB::table('ownership_attachments')->insert([...$metadata, 'franchise_ownership_id' => $ownership->id, 'path' => 'ownership-attachments/legacy.pdf']);
    $migration->up();
    expect(Schema::hasTable('transaction_attachments'))->toBeFalse()->and(Schema::hasTable('ownership_attachments'))->toBeFalse();
    $this->assertDatabaseCount('attachments', 2);
    expect($transaction->attachments()->sole()->path)->toBe('transaction-attachments/legacy.pdf');
    expect($ownership->attachments()->sole()->path)->toBe('ownership-attachments/legacy.pdf');
    $linkMigration = require database_path('migrations/2026_11_18_010000_link_transaction_attachments_to_ownerships.php');
    $linkMigration->up();
    expect($transaction->attachments()->sole()->franchise_ownership_id)->toBe($ownership->id);
    $linkMigration->down();
    expect($transaction->attachments()->sole()->franchise_ownership_id)->toBeNull();
    $migration->down();
    $this->assertDatabaseCount('transaction_attachments', 1);
    $this->assertDatabaseCount('ownership_attachments', 1);
    $migration->up();
    $this->assertDatabaseCount('attachments', 2);
});

test('attachments require a parent and linked parents must share a franchise', function () {
    $metadata = ['document_type' => 'Certificate', 'original_name' => 'certificate.pdf', 'disk' => 'local', 'path' => 'test.pdf', 'mime_type' => 'application/pdf', 'size' => 100];
    expect(fn () => Attachment::create($metadata))->toThrow(ValidationException::class);
    $franchise = Franchise::create(['franchise_number' => 'F-ATTACHMENT']);
    $other = Franchise::create(['franchise_number' => 'F-OTHER']);
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $ownership = $other->ownerships()->create(['franchise_owner_id' => $owner->id, 'started_at' => today()]);
    $transaction = $franchise->transactions()->create(['transaction_type' => 'application', 'date_filed' => today()]);
    expect(fn () => $transaction->attachments()->create([...$metadata, 'franchise_ownership_id' => $ownership->id]))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('attachments', 0);
});
