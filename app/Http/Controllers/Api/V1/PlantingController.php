<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlantingStoreRequest;
use App\Http\Requests\Api\V1\PlantingUpdateRequest;
use App\Http\Resources\Api\V1\PlantingResource;
use App\Models\Planting;
use App\Models\Plot;
use Illuminate\Http\Request;

class PlantingController extends Controller
{
    public function indexByPlot(Request $request, Plot $plot)
    {
        $this->authorize('view', $plot);

        $plantings = Planting::query()
            ->where('plot_id', $plot->id)
            ->where('is_active', 1)
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request));

        return PlantingResource::collection($plantings);
    }

    public function show(Planting $planting): PlantingResource
    {
        $this->authorize('view', $planting);

        return new PlantingResource($planting);
    }

    public function store(PlantingStoreRequest $request, Plot $plot)
    {
        $this->authorize('update', $plot);

        $planting = Planting::create([
            ...$request->validated(),
            'plot_id' => $plot->id,
        ]);

        return (new PlantingResource($planting))
            ->response()
            ->setStatusCode(201);
    }

    public function update(PlantingUpdateRequest $request, Planting $planting): PlantingResource
    {
        $this->authorize('update', $planting);

        $planting->update($request->validated());

        return new PlantingResource($planting);
    }

    public function destroy(Planting $planting)
    {
        $this->authorize('delete', $planting);

        $planting->update(['is_active' => 0]);

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);

        return max(1, min($perPage, 100));
    }
}
