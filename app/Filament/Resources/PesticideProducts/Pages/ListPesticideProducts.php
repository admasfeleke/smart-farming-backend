<?php

namespace App\Filament\Resources\PesticideProducts\Pages;

use App\Filament\Resources\PesticideProducts\PesticideProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPesticideProducts extends ListRecords
{
    protected static string $resource = PesticideProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
