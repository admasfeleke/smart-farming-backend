<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;
    private const PROTECTED_ROLE_NAMES = [
        'super_admin',
        'admin',
        'supporter',
        'expert',
        'farmer',
    ];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $normalized = strtolower(trim((string) ($data['name'] ?? '')));
        if (in_array($normalized, self::PROTECTED_ROLE_NAMES, true)) {
            throw ValidationException::withMessages([
                'name' => ['Core role names are reserved and cannot be created manually.'],
            ]);
        }

        $data['name'] = $normalized;

        return $data;
    }
}
