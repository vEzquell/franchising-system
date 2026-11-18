<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOwnershipAttachmentsRequest;
use App\Models\Attachment;
use App\Models\FranchiseOwnership;
use App\Services\Admin\OwnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnershipController extends Controller
{
    public function __construct(private readonly OwnershipService $service) {}

    public function index(Request $request): Response|JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'period' => ['nullable', Rule::in(['current', 'previous', 'upcoming'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $search = trim($filters['search'] ?? '');
        $period = $filters['period'] ?? '';
        $data = ['ownerships' => $this->service->paginate($search, $period, $request->user()->can('view transactions')), 'filters' => ['search' => $search, 'period' => $period], 'today' => today()->toDateString()];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Admin/Ownership/Index', $data);
    }

    public function show(Request $request, FranchiseOwnership $ownership): JsonResponse
    {
        return response()->json(['ownership' => $ownership->load([
            'franchise:id,franchise_number',
            'owner:id,first_name,middle_name,last_name,suffix',
            'attachments' => fn ($query) => $query
                ->when(! $request->user()->can('view transactions'), fn ($query) => $query->whereNull('franchise_transaction_id'))
                ->latest()->orderByDesc('id'),
        ])]);
    }

    public function storeAttachments(StoreOwnershipAttachmentsRequest $request, FranchiseOwnership $ownership): JsonResponse
    {
        $this->service->attach($ownership, $request->validated()['attachments']);

        return response()->json(['message' => 'Ownership documents uploaded.'], 201);
    }

    public function download(Request $request, FranchiseOwnership $ownership, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->franchise_ownership_id === $ownership->id, 404);
        abort_if($attachment->franchise_transaction_id !== null && ! $request->user()->can('view transactions'), 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
