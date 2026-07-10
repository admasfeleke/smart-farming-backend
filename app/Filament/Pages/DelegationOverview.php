<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\DelegationGuard;
use App\Support\BureaucracyProfile;
use App\Support\RegionScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DelegationOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'User & Access Management';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.delegation-overview';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && RegionScope::isSuperAdmin($user);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->backofficeDelegationQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(36),

                Tables\Columns\TextColumn::make('role.name')
                    ->label('Public-Sector Role')
                    ->formatStateUsing(fn (?string $state): string => BureaucracyProfile::roleLabelFor($state))
                    ->badge()
                    ->colors([
                        'danger' => 'super_admin',
                        'info' => 'admin',
                        'warning' => 'supporter',
                        'primary' => 'expert',
                        'success' => 'farmer',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('bureaucratic_title')
                    ->label('Office Title')
                    ->state(fn (User $record): string => BureaucracyProfile::displayTitleFor($record))
                    ->wrap()
                    ->limit(42),

                Tables\Columns\TextColumn::make('admin_level')
                    ->label('Office Level')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('region.name')
                    ->label('Primary Office Scope')
                    ->sortable()
                    ->placeholder('-')
                    ->wrap()
                    ->limit(36),

                Tables\Columns\TextColumn::make('scoped_regions_count')
                    ->label('Additional Office Scopes')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->relationship('role', 'name')
                    ->label('Public-Sector Role'),

                Tables\Filters\SelectFilter::make('region')
                    ->relationship('region', 'name')
                    ->label('Primary Office Scope'),

                Tables\Filters\SelectFilter::make('admin_level')
                    ->options([
                        'national' => 'National',
                        'region' => 'Regional State',
                        'zone' => 'Zone',
                        'woreda' => 'Woreda',
                        'kebele' => 'Kebele',
                    ])
                    ->label('Office Level'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Action::make('edit_delegation')
                    ->label('Manage Delegation')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (User $record): bool => \App\Filament\Resources\Users\UserResource::canEdit($record))
                    ->url(fn (User $record): string => route('filament.admin.resources.users.edit', ['record' => $record]))
                    ->openUrlInNewTab(false),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_delegate')
                ->label('Create Delegated User')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => \App\Filament\Resources\Users\UserResource::canCreate())
                ->url(route('filament.admin.resources.users.create')),
        ];
    }

    protected function getViewData(): array
    {
        $allUsersQuery = $this->delegationBaseQuery();
        $backofficeQuery = $this->backofficeDelegationQuery();

        $roleCounts = (clone $allUsersQuery)
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->selectRaw('LOWER(roles.name) as role_name, COUNT(users.id) as total')
            ->groupBy('role_name')
            ->pluck('total', 'role_name');

        return [
            'summary' => [
                'total_users' => (clone $allUsersQuery)->count(),
                'active_users' => (clone $allUsersQuery)->where('users.is_active', 1)->count(),
                'backoffice_users' => (clone $backofficeQuery)->count(),
                'farmers' => (int) ($roleCounts['farmer'] ?? 0),
                'system_super_administrators' => (int) ($roleCounts['super_admin'] ?? 0),
                'agriculture_office_coordinators' => (int) ($roleCounts['admin'] ?? 0),
                'development_agents' => (int) ($roleCounts['supporter'] ?? 0),
                'subject_matter_specialists' => (int) ($roleCounts['expert'] ?? 0),
            ],
        ];
    }

    private function delegationQuery(): Builder
    {
        return $this->delegationBaseQuery()
            ->with(['role', 'region'])
            ->withCount('scopedRegions');
    }

    private function backofficeDelegationQuery(): Builder
    {
        return $this->delegationQuery()
            ->whereHas('role', fn (Builder $roleQuery) => $roleQuery->whereIn('name', ['super_admin', 'admin', 'supporter', 'expert']));
    }

    private function delegationBaseQuery(): Builder
    {
        $query = User::query();

        $actor = auth()->user();
        if (! $actor instanceof User) {
            return $query->whereRaw('1 = 0');
        }
        return app(DelegationGuard::class)->scopeManageableUsersQuery($query, $actor);
    }
}
