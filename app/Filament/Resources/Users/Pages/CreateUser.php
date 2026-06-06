<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\DelegationAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return UserResource::guardDelegation($data);
    }

    protected function afterCreate(): void
    {
        DelegationAuditLogger::log(
            action: 'create_user',
            targetUserId: (int) $this->record->id,
            before: null,
            after: UserResource::snapshotDelegation($this->record),
            note: 'Delegated user account created via Filament.'
        );
    }
}
