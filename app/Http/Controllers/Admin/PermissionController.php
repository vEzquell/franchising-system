<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        abort_unless(

            $request->user()->can('view permissions'),
            403
        );

        // Validate search
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim($filters['search'] ?? '');

        // Prepare data
        $data = [
            'permissions' => Permission::query()
                ->where('guard_name', 'web')
                ->when(
                    $search !== '',
                    fn ($query) => $query->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                )
                ->withCount(['roles', 'users'])
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),

            'filters' => [
                'search' => $search,
            ],

            'canManage' => $request->user()->can('view permissions'),

            'success' => $request->session()->get('permission_success'),
        ];

        return $request->wantsJson()
            ? response()->json($data)
            : Inertia::render('Admin/Permissions/Index', $data);
    }

    public function store(SavePermissionRequest $request): RedirectResponse|JsonResponse
    {
        Permission::create([...$request->validated(), 'guard_name' => 'web']);

        return $request->wantsJson() ? response()->json(['message' => 'Permission created.'], 201)
            : back()->with('permission_success', 'Permission created.');
    }

    public function update(SavePermissionRequest $request, Permission $permission): RedirectResponse|JsonResponse
    {
        abort_unless($permission->guard_name === 'web', 404);
        $permission->update($request->validated());

        return $request->wantsJson() ? response()->json(['message' => 'Permission updated.']) : back();
    }

    public function destroy(Request $request, Permission $permission): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->can('view permissions'), 403);
        abort_unless($permission->guard_name === 'web', 404);

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            throw ValidationException::withMessages([
                'permission' => 'Remove this permission from its roles and users before deleting it.',
            ]);
        }

        $permission->delete();

        return $request->wantsJson() ? response()->json(['message' => 'Permission deleted.']) : back();
    }
}
