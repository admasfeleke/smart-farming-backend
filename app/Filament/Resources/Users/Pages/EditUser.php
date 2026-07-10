<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\DelegationAuditLogger;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
    private array $snapshotBeforeSave = [];
    private array $snapshotBeforeDelete = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => UserResource::canDelete($this->record))
                ->before(function (): void {
                    $this->snapshotBeforeDelete = UserResource::snapshotDelegation($this->record);
                    UserResource::assertCanDeleteRecord($this->record);
                })
                ->after(function (): void {
                    DelegationAuditLogger::log(
                        action: 'delete_user',
                        targetUserId: (int) $this->record->id,
                        before: $this->snapshotBeforeDelete,
                        after: null,
                        note: 'Delegated user account deleted via Filament.'
                    );
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->snapshotBeforeSave = UserResource::snapshotDelegation($this->record);
        return UserResource::guardDelegation($data, $this->record);
    }

    protected function afterSave(): void
    {
        $after = UserResource::snapshotDelegation($this->record);

        if ($this->snapshotBeforeSave !== $after) {
            DelegationAuditLogger::log(
                action: 'update_user_delegation',
                targetUserId: (int) $this->record->id,
                before: $this->snapshotBeforeSave,
                after: $after,
                note: 'Delegation details updated via Filament.'
            );
        }

        $beforeRole = (string) ($this->snapshotBeforeSave['role_name'] ?? '');
        $afterRole = (string) ($after['role_name'] ?? '');
        $roleSensitive = in_array($beforeRole, ['supporter', 'expert'], true)
            || in_array($afterRole, ['supporter', 'expert'], true);
        $delegationChanged = ($this->snapshotBeforeSave['role_id'] ?? null) !== ($after['role_id'] ?? null)
            || ($this->snapshotBeforeSave['region_id'] ?? null) !== ($after['region_id'] ?? null)
            || ($this->snapshotBeforeSave['admin_level'] ?? null) !== ($after['admin_level'] ?? null)
            || ($this->snapshotBeforeSave['scoped_region_ids'] ?? []) !== ($after['scoped_region_ids'] ?? []);

        if ($roleSensitive && $delegationChanged && ((bool) ($after['is_active'] ?? false))) {
            Notification::make()
                ->warning()
                ->title('Delegation Changed For Active Operational Account')
                ->body('Development Agent / Subject Matter Specialist assignment changed. Ensure workload handover and regional coverage are still valid.')
                ->send();
        }
    }
}
