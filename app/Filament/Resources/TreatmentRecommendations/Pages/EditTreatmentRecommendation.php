<?php

namespace App\Filament\Resources\TreatmentRecommendations\Pages;

use App\Filament\Resources\TreatmentRecommendations\TreatmentRecommendationResource;
use App\Support\RegionScope;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTreatmentRecommendation extends EditRecord
{
    protected static string $resource = TreatmentRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
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
