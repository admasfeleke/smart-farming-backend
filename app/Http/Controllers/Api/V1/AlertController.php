<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlertResource;
use App\Models\Alert;
use App\Support\RegionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Alert::class);

        $user = $request->user();
        $query = Alert::query()
            ->orderBy('triggered_at', 'desc');

        if (RegionScope::roleName($user) === 'farmer') {
            $query->where(function (Builder $scope) use ($user): void {
                $scope
                    ->whereHas('diseaseReport.plot.farm', fn (Builder $farmQuery) => $farmQuery->where('farmer_id', $user->id))
                    ->orWhereHas('farm', fn (Builder $farmQuery) => $farmQuery->where('farmer_id', $user->id))
                    ->orWhereHas('plot.farm', fn (Builder $farmQuery) => $farmQuery->where('farmer_id', $user->id))
                    ->orWhereHas('planting.plot.farm', fn (Builder $farmQuery) => $farmQuery->where('farmer_id', $user->id));
            });
        } else {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $scope) use ($regionIds): void {
                    $scope
                        ->whereHas('diseaseReport.plot.farm', fn (Builder $farmQuery) => $farmQuery->whereIn('region_id', $regionIds))
                        ->orWhereHas('farm', fn (Builder $farmQuery) => $farmQuery->whereIn('region_id', $regionIds))
                        ->orWhereHas('plot.farm', fn (Builder $farmQuery) => $farmQuery->whereIn('region_id', $regionIds))
                        ->orWhereHas('planting.plot.farm', fn (Builder $farmQuery) => $farmQuery->whereIn('region_id', $regionIds));
                });
            }
        }

        $alerts = $query->paginate($this->perPage($request));

        return AlertResource::collection($alerts);
    }

    public function acknowledge(Request $request, Alert $alert): AlertResource|JsonResponse
    {
        $this->authorize('update', $alert);

        if ($alert->status === 'resolved') {
            return response()->json([
                'message' => 'Alert already resolved.',
            ], 422);
        }

        if ($alert->status !== 'acknowledged') {
            $alert->update([
                'status' => 'acknowledged',
                'acknowledged_by' => $request->user()->id,
                'acknowledged_at' => now(),
            ]);
        }

        return new AlertResource($alert->fresh());
    }

    public function resolve(Request $request, Alert $alert): AlertResource|JsonResponse
    {
        $this->authorize('update', $alert);

        if ($alert->status === 'resolved') {
            return response()->json([
                'message' => 'Alert already resolved.',
            ], 422);
        }

        if ($alert->status === 'open') {
            return response()->json([
                'message' => 'Alert must be acknowledged before resolving.',
            ], 422);
        }

        $alert->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return new AlertResource($alert->fresh());
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);

        return max(1, min($perPage, 100));
    }
}
