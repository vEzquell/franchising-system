<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveFranchiseRequest;
use App\Models\Franchise;
use App\Models\Liner;
use App\Services\Admin\FranchiseServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FranchiseController extends Controller
{
    public function __construct(private readonly FranchiseServices $franchiseServices) {}

    public function index(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'expired', 'suspended', 'unavailable'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? '';
        $data = [
            'franchises' => $this->franchiseServices->paginate($search, $status),
            'filters' => ['search' => $search, 'status' => $status],
        ];

        return $request->wantsJson()
            ? response()->json($data)
            : Inertia::render('Admin/Franchise/Index', $data);
    }

    public function transactions(Request $request, Franchise $franchise): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return response()->json(['transactions' => $this->franchiseServices->transactions($franchise)]);
    }

    public function linerOptions(Franchise $franchise): JsonResponse
    {
        return response()->json([
            'liner_id' => $franchise->liner_id,
            'liners' => Liner::query()->orderBy('name')->get(['id', 'name', 'route']),
        ]);
    }

    public function updateLiner(Request $request, Franchise $franchise): JsonResponse
    {
        $data = $request->validate([
            'liner_id' => ['bail', 'present', 'nullable', 'integer', Rule::exists(Liner::class, 'id')],
        ]);

        $franchise->liner()->associate($data['liner_id']);
        $franchise->save();

        return response()->json(['message' => $data['liner_id'] === null ? 'Liner unlinked.' : 'Liner linked.']);
    }

    public function store(SaveFranchiseRequest $request): JsonResponse
    {
        $this->franchiseServices->create($request->validated());

        return response()->json(['message' => 'Franchise created.'], 201);
    }

    public function update(SaveFranchiseRequest $request, Franchise $franchise): JsonResponse
    {
        $this->franchiseServices->update($franchise, $request->validated());

        return response()->json(['message' => 'Franchise updated.']);
    }
}
