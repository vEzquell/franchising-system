<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTransactionTypeRequest;
use App\Models\TransactionType;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TransactionTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/TransactionTypes/Index', [
            'transactionTypes' => TransactionType::query()->orderBy('name')->get(['code', 'name', 'is_active']),
            'builtInCodes' => array_keys(TransactionType::BUILT_IN_TYPES),
        ]);
    }

    public function store(SaveTransactionTypeRequest $request): JsonResponse
    {
        $transactionType = TransactionType::create($request->validated());

        return response()->json(['transactionType' => $transactionType, 'message' => 'Transaction type added.'], 201);
    }

    public function update(SaveTransactionTypeRequest $request, TransactionType $transactionType): JsonResponse
    {
        $transactionType->update($request->validated());

        return response()->json(['transactionType' => $transactionType, 'message' => 'Transaction type updated.']);
    }
}
