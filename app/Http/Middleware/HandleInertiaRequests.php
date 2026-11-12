<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'welcome';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'username'),
            ],
            'navigation' => [
                'dashboard' => route('dashboard', absolute: false),
                'users' => route('users', absolute: false),
                'logout' => route('logout', absolute: false),
                'franchises.index' => Route::has('franchises.index')
                    ? route('franchises.index', absolute: false) : null,
                'settings' => Route::has('settings')
                    ? route('settings', absolute: false) : null,
            ],
        ];
    }
}
