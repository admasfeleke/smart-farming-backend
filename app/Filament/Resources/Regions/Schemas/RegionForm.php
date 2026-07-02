<?php

namespace App\Filament\Resources\Regions\Schemas;

use App\Models\Region;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RegionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(100),

            Select::make('level')
                ->required()
                ->live()
                ->afterStateUpdated(fn ($state, callable $set) => $set('parent_id', null))
                ->options([
                    'region' => 'Region',
                    'zone' => 'Zone',
                    'special_woreda' => 'Special Woreda',
                    'woreda' => 'Woreda',
                    'kebele' => 'Kebele',
                    'ftc' => 'Farmer Training Center',
                ]),

            Select::make('parent_id')
                ->label('Parent Region')
                ->options(function (Get $get): array {
                    $level = strtolower((string) $get('level'));
                    $expectedParentLevels = Region::expectedParentLevels($level);
                    if ($expectedParentLevels === []) {
                        return [];
                    }

                    $currentId = $get('id');

                    return Region::query()
                        ->whereIn('level', $expectedParentLevels)
                        ->where('is_active', 1)
                        ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all();
                })
                ->searchable()
                ->required(fn (Get $get): bool => strtolower((string) $get('level')) !== Region::LEVEL_REGION)
                ->disabled(fn (Get $get): bool => strtolower((string) $get('level')) === Region::LEVEL_REGION)
                ->helperText(function (Get $get): ?string {
                    $level = strtolower((string) $get('level'));
                    return match ($level) {
                        Region::LEVEL_REGION => 'Top level. No parent required.',
                        Region::LEVEL_ZONE => 'Zone parent must be a region.',
                        Region::LEVEL_SPECIAL_WOREDA => 'Special woreda parent must be a region.',
                        Region::LEVEL_WOREDA => 'Woreda parent must be a zone.',
                        Region::LEVEL_KEBELE => 'Kebele parent must be a woreda or special woreda.',
                        Region::LEVEL_FTC => 'FTC parent must be a woreda or special woreda.',
                        default => 'Select level first.',
                    };
                }),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }
}
