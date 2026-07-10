<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Services\DelegationGuard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            \Filament\Schemas\Components\Section::make('User Information')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(100),

                    \Filament\Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),

                    \Filament\Forms\Components\TextInput::make('email')
                        ->email()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100),

                    \Filament\Forms\Components\Select::make('role_id')
                        ->label('Role')
                        ->required()
                        ->searchable()
                        ->options(fn (): array => self::availableRoleOptions())
                        ->live()
                        ->afterStateUpdated(function (callable $set): void {
                            $set('admin_level', null);
                            $set('region_id', null);
                            $set('scopedRegions', []);
                        }),

                    \Filament\Forms\Components\Select::make('admin_level')
                        ->label('Admin Level')
                        ->options([
                            'national' => 'National',
                            'region' => 'Region',
                            'zone' => 'Zone',
                            'woreda' => 'Woreda',
                            'kebele' => 'Kebele',
                        ])
                        ->searchable()
                        ->visible(fn (callable $get): bool => self::isBackofficeRoleId($get('role_id')))
                        ->required(fn (callable $get): bool => self::isBackofficeRoleId($get('role_id')))
                        ->live()
                        ->afterStateUpdated(function (callable $set): void {
                            $set('region_id', null);
                            $set('scopedRegions', []);
                        }),

                    \Filament\Forms\Components\Select::make('technical_domain')
                        ->label('Technical Domain')
                        ->options(fn (): array => self::technicalDomainOptions())
                        ->searchable()
                        ->visible(fn (callable $get): bool => self::roleName($get('role_id')) === 'expert')
                        ->helperText('Used to derive real SMS titles such as Crop Protection Specialist or Soil Health Specialist.'),

                    \Filament\Forms\Components\TextInput::make('position_title')
                        ->label('Specific Position Title')
                        ->maxLength(160)
                        ->visible(fn (callable $get): bool => self::isBackofficeRoleId($get('role_id')))
                        ->helperText('Optional. If provided, this exact public-sector title is shown instead of the derived title.'),

                    \Filament\Forms\Components\Select::make('region_id')
                        ->label('Primary Office Scope')
                        ->options(fn (callable $get): array => self::availablePrimaryRegionOptions(
                            $get('role_id'),
                            $get('admin_level')
                        ))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(fn (callable $get): bool => self::roleNeedsRegion($get('role_id'))),

                    \Filament\Forms\Components\Select::make('scopedRegions')
                        ->label('Additional Office Scopes')
                        ->options(fn (callable $get): array => self::availableScopedRegionOptions($get('region_id')))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (callable $get): bool => self::isBackofficeRoleId($get('role_id')))
                        ->helperText('Delegate extra zones, special woredas, woredas, kebeles, or FTCs under the selected primary office scope.'),

                    \Filament\Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),

            \Filament\Schemas\Components\Section::make('Security')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('password')
                        ->password()
                        ->label('Password')
                        ->dehydrateStateUsing(
                            fn ($state) => filled($state) ? Hash::make($state) : null
                        )
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Leave empty to keep existing password'),
                ]),
        ]);
    }

    private static function availableRoleOptions(): array
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }
        return app(DelegationGuard::class)->availableRoleOptions($actor);
    }

    private static function availablePrimaryRegionOptions($roleId, $adminLevel): array
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }
        $roleName = app(DelegationGuard::class)->roleNameById((int) $roleId);

        return app(DelegationGuard::class)->availablePrimaryRegionOptions(
            $actor,
            $roleName,
            is_string($adminLevel) ? $adminLevel : null
        );
    }

    private static function availableScopedRegionOptions($primaryRegionId): array
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }

        $primaryId = (int) $primaryRegionId;
        if ($primaryId <= 0) {
            return [];
        }
        return app(DelegationGuard::class)->availableScopedRegionOptions($actor, $primaryId);
    }

    private static function technicalDomainOptions(): array
    {
        return collect((array) config('central_ethiopia_bureaucracy.technical_domains', []))
            ->mapWithKeys(fn ($label, $key): array => [(string) $key => (string) $label])
            ->all();
    }

    private static function roleName($roleId): string
    {
        return app(DelegationGuard::class)->roleNameById((int) $roleId) ?? '';
    }
    private static function roleNeedsRegion($roleId): bool
    {
        $name = app(DelegationGuard::class)->roleNameById((int) $roleId);

        return in_array($name, ['admin', 'supporter', 'expert', 'farmer'], true);
    }

    private static function isBackofficeRoleId($roleId): bool
    {
        $name = app(DelegationGuard::class)->roleNameById((int) $roleId);

        return in_array($name, ['super_admin', 'admin', 'supporter', 'expert'], true);
    }
}
