<?php

namespace App\Filament\Resources\TreatmentRecommendations\Pages;

use App\Filament\Resources\TreatmentRecommendations\TreatmentRecommendationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTreatmentRecommendations extends ListRecords
{
    protected static string $resource = TreatmentRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
