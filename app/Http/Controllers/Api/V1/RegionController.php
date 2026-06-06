<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RegionResource;
use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index(Request $request)
    {
        $regions = Region::query()
            ->where('is_active', 1)
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return RegionResource::collection($regions);
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 200);

        return max(1, min($perPage, 500));
    }
}
