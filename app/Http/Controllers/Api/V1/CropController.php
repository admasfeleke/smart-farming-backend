<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CropResource;
use App\Models\Crop;
use Illuminate\Http\Request;

class CropController extends Controller
{
    public function index(Request $request)
    {
        $crops = Crop::query()
            ->where('is_active', 1)
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return CropResource::collection($crops);
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 100);

        return max(1, min($perPage, 200));
    }
}
