<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveOwnerRequest;
use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\Liner;
use App\Models\Vehicle;
use App\Services\Admin\OwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function __construct(private readonly OwnerService $ownerService) {}

    public function index(Request $request): Response|JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $search = trim($filters['search'] ?? '');
        $data = ['owners' => $this->ownerService->paginate($search), 'filters' => ['search' => $search]];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Admin/Owner/Index', $data);
    }

    public function franchises(Request $request): JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:50']]);
        $search = trim($filters['search'] ?? '');

        return response()->json(['franchises' => Franchise::query()->where('franchise_number', 'like', '%'.$search.'%')
            ->orderBy('franchise_number')->limit(20)->get(['id', 'franchise_number', 'date_issued', 'expiration_date'])]);
    }

    public function nameMatches(Request $request): JsonResponse
    {
        $names = $request->validate([
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(['owners' => $this->ownerService->findNameMatches(
            trim($names['first_name'] ?? ''),
            trim($names['last_name'] ?? ''),
        )]);
    }

    public function assignFranchise(SaveOwnerRequest $request, FranchiseOwner $owner): JsonResponse
    {
        $this->ownerService->assignFranchise($owner, $request->validated());

        return response()->json(['message' => 'Franchise assigned.'], 201);
    }

    public function setupOptions(Franchise $franchise): JsonResponse
    {
        return response()->json([
            'current_vehicle' => $franchise->vehicleAssignments()->current()->with('vehicle')
                ->latest('started_at')->orderByDesc('id')->first()?->vehicle,
            'vehicles' => Vehicle::query()->orderBy('id')->get(['id', 'make_type', 'plate_number', 'motor_number', 'chassis_number']),
            'liner_id' => $franchise->liner_id,
            'liners' => Liner::query()->orderBy('name')->get(['id', 'name', 'route']),
        ]);
    }

    public function store(SaveOwnerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hasFranchise = isset($data['franchise_id']);
        $owner = $hasFranchise ? $this->ownerService->createWithFranchise($data) : $this->ownerService->create($data);

        return response()->json([
            'message' => $hasFranchise ? 'Owner created and franchise linked.' : 'Owner created.',
            'owner' => $owner->only(['id', 'first_name', 'middle_name', 'last_name', 'suffix']),
        ], 201);
    }

    public function update(SaveOwnerRequest $request, FranchiseOwner $owner): JsonResponse
    {
        $this->ownerService->update($owner, $request->validated());

        return response()->json(['message' => 'Owner updated.']);
    }
}
