<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFranchiseTransactionRequest;
use App\Models\Attachment;
use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\FranchiseTransaction;
use App\Models\Vehicle;
use App\Services\Admin\FranchiseTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FranchiseTransactionController extends Controller
{
    public function show(Request $request, FranchiseTransaction $transaction): JsonResponse
    {
        return response()->json([
            'transaction' => $transaction->load(['franchise', 'vehicleDetails.vehicle', 'attachments', 'renewal.owner', 'substitution.previousVehicle', 'substitution.newVehicle', 'amendment.changes', 'amendment.transfer']),
            'next_statuses' => $request->user()->can('process transactions') ? $transaction->nextStatuses() : [],
        ]);
    }

    public function updateStatus(Request $request, FranchiseTransaction $transaction, FranchiseTransactionService $service): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string']]);
        $service->updateStatus($transaction, $data['status'], $request->user());

        return response()->json(['message' => 'Transaction status updated.']);
    }

    public function download(FranchiseTransaction $transaction, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->franchise_transaction_id === $transaction->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function index(Request $request, FranchiseTransactionService $service): Response|JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'under_review', 'approved', 'rejected', 'cancelled'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? '';
        $data = ['transactions' => $service->paginate($search, $status), 'filters' => ['search' => $search, 'status' => $status]];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Admin/Transactions/Index', $data);
    }

    public function franchises(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:50'], 'id' => ['nullable', 'integer', 'min:1']]);
        $query = Franchise::query()->select(['id', 'franchise_number', 'expiration_date']);
        if (! empty($filters['id'])) {
            $query->whereKey($filters['id']);
        } else {
            $search = trim($filters['search'] ?? '');
            $query->where('franchise_number', 'like', '%'.$search.'%');
        }

        return response()->json(['franchises' => $query->orderBy('franchise_number')->limit(20)->get()]);
    }

    public function storeSelected(StoreFranchiseTransactionRequest $request, FranchiseTransactionService $service): JsonResponse
    {
        $data = $request->validated();
        $service->create(Franchise::findOrFail($data['franchise_id']), $data);

        return response()->json(['message' => 'Transaction added.'], 201);
    }

    public function options(Franchise $franchise): JsonResponse
    {
        return response()->json([
            'current_vehicles' => $franchise->vehicleAssignments()->whereDate('started_at', '<=', today())->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', today()))->with('vehicle')->get()->pluck('vehicle'),
            'owners' => FranchiseOwner::orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']),
            'current_owner_ids' => $franchise->ownerships()->whereDate('started_at', '<=', today())
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', today()))->pluck('franchise_owner_id'),
            'vehicles' => Vehicle::orderBy('id')->get(['id', 'make_type', 'plate_number', 'motor_number', 'chassis_number']),
        ]);
    }

    public function store(StoreFranchiseTransactionRequest $request, Franchise $franchise, FranchiseTransactionService $service): JsonResponse
    {
        $service->create($franchise, $request->validated());

        return response()->json(['message' => 'Transaction added.'], 201);
    }
}
