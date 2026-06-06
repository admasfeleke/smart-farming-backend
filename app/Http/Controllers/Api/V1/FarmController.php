<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FarmStoreRequest;
use App\Http\Requests\Api\V1\FarmUpdateRequest;
use App\Http\Resources\Api\V1\FarmResource;
use App\Models\Farm;
use App\Support\RegionScope;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Farm::class);

        $user = $request->user();
        $query = Farm::query()
            ->where('is_active', 1)
            ->withCount([
                'plots' => fn ($query) => $query->where('is_active', 1),
            ]);

        if (RegionScope::roleName($user) === 'farmer') {
            $query->where('farmer_id', $user->id);
        } else {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('region_id', $regionIds);
            }
        }

        $farms = $query
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request));

        return FarmResource::collection($farms);
    }

    public function show(Farm $farm): FarmResource
    {
        $this->authorize('view', $farm);

        return new FarmResource($farm);
    }

    public function store(FarmStoreRequest $request)
    {
        $this->authorize('create', Farm::class);

        $farm = Farm::create([
            ...$request->validated(),
            'farmer_id' => $request->user()->id,
        ]);

        $user = $request->user();
        if (empty($user->region_id)) {
            $user->forceFill(['region_id' => (int) $farm->region_id])->save();
        }

        return (new FarmResource($farm))
            ->response()
            ->setStatusCode(201);
    }

    public function update(FarmUpdateRequest $request, Farm $farm): FarmResource
    {
        $this->authorize('update', $farm);

        $farm->update($request->validated());

        return new FarmResource($farm);
    }

    public function destroy(Farm $farm)
    {
        $this->authorize('delete', $farm);

        $farm->update(['is_active' => 0]);

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);

        return max(1, min($perPage, 100));
    }
}
