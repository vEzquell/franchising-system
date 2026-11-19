<?php

namespace App\Services\Admin;

use App\Models\FranchiseOwnership;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OwnershipService
{
    public function paginate(string $search = '', string $period = '', bool $includeTransactionAttachments = false): LengthAwarePaginator
    {
        $query = FranchiseOwnership::query()->with(['franchise:id,franchise_number', 'owner:id,first_name,middle_name,last_name,suffix'])
            ->withCount(['attachments' => fn (Builder $query) => $query->when(! $includeTransactionAttachments, fn (Builder $query) => $query->whereNull('franchise_transaction_id'))]);
        foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(fn (Builder $query) => $query->whereHas('franchise', fn (Builder $query) => $query->where('franchise_number', 'like', '%'.$term.'%'))
                ->orWhereHas('owner', fn (Builder $query) => $query->where('first_name', 'like', '%'.$term.'%')->orWhere('middle_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('suffix', 'like', '%'.$term.'%')));
        }
        if ($period === 'current') {
            $query->whereDate('started_at', '<=', today())->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', today()));
        }
        if ($period === 'previous') {
            $query->whereDate('ended_at', '<=', today());
        }
        if ($period === 'upcoming') {
            $query->whereDate('started_at', '>', today());
        }

        return $query->orderByDesc('started_at')->orderByDesc('id')->paginate(10)->withQueryString();
    }

    public function attach(FranchiseOwnership $ownership, array $uploads): void
    {
        $paths = [];
        try {
            DB::transaction(function () use ($ownership, $uploads, &$paths) {
                $ownership = FranchiseOwnership::whereKey($ownership->id)->lockForUpdate()->firstOrFail();
                app(AttachmentService::class)->store($ownership->attachments(), $uploads, 'ownership-attachments/'.$ownership->id, $paths);
            });
        } catch (\Throwable $exception) {
            if ($paths) {
                Storage::disk('local')->delete($paths);
            }
            throw $exception;
        }
    }

    public function deleteAttachment(FranchiseOwnership $ownership, int $attachmentId, bool $canDeleteTransactionAttachments): void
    {
        DB::transaction(function () use ($ownership, $attachmentId, $canDeleteTransactionAttachments) {
            $attachment = $ownership->attachments()->lockForUpdate()->findOrFail($attachmentId);
            abort_if($attachment->franchise_transaction_id !== null && ! $canDeleteTransactionAttachments, 403);

            if (! $attachment->delete() || ! Storage::disk($attachment->disk)->delete($attachment->path)) {
                throw new \RuntimeException('Unable to delete attachment.');
            }
        });
    }
}
