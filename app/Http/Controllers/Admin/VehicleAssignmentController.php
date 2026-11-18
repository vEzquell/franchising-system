<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignVehicleRequest;
use App\Models\Franchise;
use App\Models\Vehicle;
use App\Services\Admin\VehicleService;
use Illuminate\Http\JsonResponse;

class VehicleAssignmentController extends Controller
{
    public function options(Franchise $franchise): JsonResponse
    {
        return response()->json(['vehicles' => Vehicle::orderBy('id')->get()]);
    }

    public function store(AssignVehicleRequest $request, Franchise $franchise, VehicleService $service): JsonResponse
    {
        $service->assign($franchise, $request->validated());

        return response()->json(['message' => 'Vehicle assigned.'], 201);
    }
}
