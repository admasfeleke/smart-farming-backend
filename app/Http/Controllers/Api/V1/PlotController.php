<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlotStoreRequest;
use App\Http\Requests\Api\V1\PlotUpdateRequest;
use App\Http\Resources\Api\V1\PlotResource;
use App\Models\Farm;
use App\Models\Plot;
use Illuminate\Http\Request;

class PlotController extends Controller
{
    public function indexByFarm(Request $request, Farm $farm)
    {
        $this->authorize('view', $farm);

        $plots = Plot::query()
            ->where('farm_id', $farm->id)
            ->where('is_active', 1)
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request));

        return PlotResource::collection($plots);
    }

    public function show(Plot $plot): PlotResource
    {
        $this->authorize('view', $plot);

        return new PlotResource($plot);
    }

    public function store(PlotStoreRequest $request, Farm $farm)
    {
        $this->authorize('update', $farm);

        $plot = Plot::create([
            ...$request->validated(),
            'farm_id' => $farm->id,
        ]);

        return (new PlotResource($plot))
            ->response()
            ->setStatusCode(201);
    }

    public function update(PlotUpdateRequest $request, Plot $plot): PlotResource
    {
        $this->authorize('update', $plot);

        $plot->update($request->validated());

        return new PlotResource($plot);
    }

    public function destroy(Plot $plot)
    {
        $this->authorize('delete', $plot);

        $plot->update(['is_active' => 0]);

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);

        return max(1, min($perPage, 100));
    }
}
