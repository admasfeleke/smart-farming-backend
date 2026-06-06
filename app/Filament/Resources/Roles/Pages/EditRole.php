<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Keep core operational roles immutable to avoid breaking auth/authorization flows.
     */
    private const PROTECTED_ROLE_NAMES = [
        'super_admin',
        'admin',
        'supporter',
        'expert',
        'farmer',
    ];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->before(function (): void {
                    /** @var Role $role */
                    $role = $this->record;
                    $roleName = strtolower((string) $role->name);

                    if (in_array($roleName, self::PROTECTED_ROLE_NAMES, true)) {
                        throw ValidationException::withMessages([
                            'name' => ['Core role cannot be deleted.'],
                        ]);
                    }

                    $assignedUsers = $role->users()->count();
                    if ($assignedUsers > 0) {
                        throw ValidationException::withMessages([
                            'name' => ["Role is assigned to {$assignedUsers} user(s) and cannot be deleted."],
                        ]);
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Role $role */
        $role = $this->record;
        $currentName = strtolower((string) $role->name);
        $nextName = strtolower(trim((string) ($data['name'] ?? $role->name)));

        if (in_array($currentName, self::PROTECTED_ROLE_NAMES, true) && $currentName !== $nextName) {
            throw ValidationException::withMessages([
                'name' => ['Core role name cannot be renamed.'],
            ]);
        }

        if (! in_array($currentName, self::PROTECTED_ROLE_NAMES, true)
            && in_array($nextName, self::PROTECTED_ROLE_NAMES, true)) {
            throw ValidationException::withMessages([
                'name' => ['Core role names are reserved and cannot be assigned.'],
            ]);
        }

        $data['name'] = $nextName;

        return $data;
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->success()
            ->title('Role updated')
            ->send();
    }
}
