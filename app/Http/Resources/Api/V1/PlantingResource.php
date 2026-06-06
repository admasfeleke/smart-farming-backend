<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plot_id' => $this->plot_id,
            'crop_id' => $this->crop_id,
            'planting_date' => $this->planting_date,
            'expected_harvest_date' => $this->expected_harvest_date,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
