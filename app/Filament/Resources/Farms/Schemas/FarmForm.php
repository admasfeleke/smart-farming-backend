<?php

namespace App\Filament\Resources\Farms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FarmForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('farmer_id')
        ->label('Farmer')
        ->relationship(
        name: 'farmer',
        titleAttribute: 'name',
        modifyQueryUsing: fn ($query) =>
            $query->whereHas('role', fn ($q) => $q->where('name', 'farmer'))
                  ->where('is_active', 1)
             )
             ->required()
             ->searchable()
             ->preload(),

            Select::make('region_id')
                ->relationship('region', 'name')
                ->required()
                ->searchable(),

            TextInput::make('farm_name')
                ->required()
                ->maxLength(100),

            TextInput::make('latitude')
                ->numeric(),

            TextInput::make('longitude')
                ->numeric(),

            Toggle::make('is_active')
                ->default(true),
        ]);
    }
}
