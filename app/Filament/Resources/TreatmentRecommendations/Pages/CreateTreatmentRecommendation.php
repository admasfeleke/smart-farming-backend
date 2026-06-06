<?php

namespace App\Filament\Resources\TreatmentRecommendations\Pages;

use App\Filament\Resources\TreatmentRecommendations\TreatmentRecommendationResource;
use App\Support\RegionScope;
use Filament\Resources\Pages\CreateRecord;

class CreateTreatmentRecommendation extends CreateRecord
{
    protected static string $resource = TreatmentRecommendationResource::class;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $role = auth()->user() ? RegionScope::roleName(auth()->user()) : '';

        if ($role === 'expert') {
            $data['approval_status'] = 'draft';
            $data['approved_at'] = null;
            $data['approved_by'] = null;
        } elseif (in_array($role, ['super_admin', 'admin'], true) && ($data['approval_status'] ?? null) === 'approved') {
            $data['approved_at'] = $data['approved_at'] ?? now();
            $data['approved_by'] = auth()->id();
        }

        return $data;
    }
}
