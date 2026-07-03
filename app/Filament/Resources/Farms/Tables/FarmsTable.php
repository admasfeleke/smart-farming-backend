<?php

namespace App\Filament\Resources\Farms\Tables;

use Filament\Tables;
use Filament\Tables\Table;

class FarmsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('farm_name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('farmer.name')
                    ->label('Farmer')
                    ->sortable()
                    ->wrap()
                    ->limit(32),

                Tables\Columns\TextColumn::make('region.name')
                    ->label('Administrative Unit')
                    ->sortable()
                    ->wrap()
                    ->limit(36),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
