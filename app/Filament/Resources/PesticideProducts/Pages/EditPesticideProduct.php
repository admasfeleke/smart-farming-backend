<?php

namespace App\Filament\Resources\PesticideProducts\Pages;

use App\Filament\Resources\PesticideProducts\PesticideProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPesticideProduct extends EditRecord
{
    protected static string $resource = PesticideProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
