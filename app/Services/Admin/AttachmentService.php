<?php

namespace App\Services\Admin;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AttachmentService
{
    public function store(HasMany $attachments, array $uploads, string $directory, array &$storedPaths, ?int $ownershipId = null): void
    {
        foreach ($uploads as $upload) {
            $file = $upload['file'];
            $path = $file->store($directory, 'local');
            if (! $path) {
                throw new \RuntimeException('Unable to store attachment.');
            }
            $storedPaths[] = $path;
            $attachments->create([
                ...($ownershipId !== null ? ['franchise_ownership_id' => $ownershipId] : []),
                ...(isset($upload['requirement_id']) ? ['requirement_id' => $upload['requirement_id']] : []),
                'document_name' => $upload['document_name'], 'document_type' => $upload['document_type'] ?? 'PDF', 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
            ]);
        }
    }
}
