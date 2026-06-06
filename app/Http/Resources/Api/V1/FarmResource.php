<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farmer_id' => $this->farmer_id,
            'region_id' => $this->region_id,
            'farm_name' => $this->farm_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'area_hectares' => $this->area_hectares,
            'farm_type' => $this->farm_type,
            'is_active' => $this->is_active,
            'plots_count' => $this->whenCounted('plots'),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
