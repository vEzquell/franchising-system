<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLinerRequest;
use App\Models\Liner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LinerController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $search = trim($filters['search'] ?? '');

        $liners = Liner::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('name', '%'.$search.'%')
                        ->orWhereLike('route', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(10, ['id', 'name', 'route'])
            ->withQueryString();

        $data = ['liners' => $liners, 'filters' => ['search' => $search]];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Admin/Liner/Index', $data);
    }

    public function store(SaveLinerRequest $request): JsonResponse
    {
        Liner::create($request->validated());

        return response()->json(['message' => 'Liner added.'], 201);
    }

    public function update(SaveLinerRequest $request, Liner $liner): JsonResponse
    {
        $liner->update($request->validated());

        return response()->json(['message' => 'Liner updated.']);
    }
}
