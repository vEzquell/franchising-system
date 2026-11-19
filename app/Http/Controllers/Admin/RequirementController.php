<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveRequirementRequest;
use App\Models\Requirement;
use App\Models\TransactionType;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class RequirementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Requirements/Index', [
            'transactionTypes' => TransactionType::query()->orderBy('name')->pluck('name', 'code'),
            'requirements' => Requirement::query()->orderBy('name')->get(['id', 'transaction_type', 'name', 'description']),
        ]);
    }

    public function store(SaveRequirementRequest $request): JsonResponse
    {
        $requirement = Requirement::create($request->validated());

        return response()->json(['requirement' => $requirement, 'message' => 'Requirement added.'], 201);
    }

    public function update(SaveRequirementRequest $request, Requirement $requirement): JsonResponse
    {
        $requirement->update($request->validated());

        return response()->json(['requirement' => $requirement, 'message' => 'Requirement updated.']);
    }
}
