<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Services\DelegationGuard;
use App\Models\Role;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | UnitEnum | null $navigationGroup = 'User & Access Management';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && \App\Support\RegionScope::isSuperAdmin($user);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && \App\Support\RegionScope::isSuperAdmin($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['role', 'region']);
        $user = auth()->user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }
        return app(DelegationGuard::class)->scopeManageableUsersQuery($query, $user);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && \App\Support\RegionScope::isSuperAdmin($user);
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof User) {
            return false;
        }

        $actor = auth()->user();
        return $actor instanceof User
            && \App\Support\RegionScope::isSuperAdmin($actor);
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof User) {
            return false;
        }

        $actor = auth()->user();
        return $actor instanceof User
            && \App\Support\RegionScope::isSuperAdmin($actor);
    }

    public static function guardDelegation(array $data, ?User $target = null): array
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! \App\Support\RegionScope::isSuperAdmin($actor)) {
            throw ValidationException::withMessages([
                'role_id' => ['Unauthorized delegation attempt.'],
            ]);
        }

        return app(DelegationGuard::class)->guardDelegation($actor, $data, $target);
    }

    public static function assertCanDeleteRecord(User $record): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! \App\Support\RegionScope::isSuperAdmin($actor)) {
            throw ValidationException::withMessages([
                'name' => ['Unauthorized deletion attempt.'],
            ]);
        }

        app(DelegationGuard::class)->assertCanDeleteRecord($actor, $record);
    }

    public static function snapshotDelegation(User $user): array
    {
        $scopedRegionIds = [];
        if ($user->exists && SchemaFacade::hasTable('user_region_scopes')) {
            $scopedRegionIds = $user->scopedRegions()
                ->pluck('regions.id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
        }

        $roleName = strtolower((string) optional($user->role)->name);
        if ($roleName === '' && ! empty($user->role_id)) {
            $roleName = self::roleNameById((int) $user->role_id) ?? '';
        }

        return [
            'role_id' => $user->role_id !== null ? (int) $user->role_id : null,
            'role_name' => $roleName,
            'region_id' => $user->region_id !== null ? (int) $user->region_id : null,
            'admin_level' => $user->admin_level,
            'is_active' => (bool) $user->is_active,
            'scoped_region_ids' => $scopedRegionIds,
        ];
    }

    private static function roleNameById(int $roleId): ?string
    {
        if ($roleId <= 0) {
            return null;
        }

        $role = Role::query()->find($roleId);

        return $role ? strtolower((string) $role->name) : null;
    }
}
