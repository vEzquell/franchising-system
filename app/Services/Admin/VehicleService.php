<?php

namespace App\Services\Admin;

use App\Models\Franchise;
use App\Models\FranchiseSubstitution;
use App\Models\FranchiseVehicleAssignment;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleService
{
    public function substitute(Franchise $franchise, FranchiseSubstitution $substitution): void
    {
        $date = $substitution->substitution_date->toDateString();
        $assignments = $franchise->vehicleAssignments()
            ->where('vehicle_id', $substitution->previous_vehicle_id)
            ->whereDate('started_at', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', $date))
            ->lockForUpdate()
            ->get();

        if ($assignments->count() !== 1) {
            throw ValidationException::withMessages(['status' => 'The previous vehicle is no longer assigned on the substitution date.']);
        }

        $assignments->sole()->update(['ended_at' => $date]);
        $this->assign($franchise, [
            'vehicle' => ['mode' => 'existing', 'id' => $substitution->new_vehicle_id],
            'started_at' => $date,
        ]);
    }

    public function resolve(array $data): Vehicle
    {
        if ($data['mode'] === 'existing') {
            return Vehicle::findOrFail($data['id']);
        }

        try {
            return Vehicle::create(Arr::only($data, ['make_type', 'plate_number', 'motor_number', 'chassis_number']));
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getPrevious()->getMessage(), 'vehicles_details_unique')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'vehicle.plate_number' => 'A vehicle with these same details already exists.',
            ]);
        }
    }

    public function assign(Franchise $franchise, array $data): FranchiseVehicleAssignment
    {
        return DB::transaction(function () use ($franchise, $data) {
            $franchise = Franchise::query()->lockForUpdate()->findOrFail($franchise->id);
            $vehicle = $this->resolve($data['vehicle']);
            Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            $overlap = FranchiseVehicleAssignment::where(fn ($query) => $query->where('franchise_id', $franchise->id)->orWhere('vehicle_id', $vehicle->id))
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>', $data['started_at']))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['vehicle.id' => 'The franchise or vehicle already has an overlapping assignment. Use a substitution request to replace it.']);
            }

            return $franchise->vehicleAssignments()->create(['vehicle_id' => $vehicle->id, 'started_at' => $data['started_at']]);
        });
    }
}
