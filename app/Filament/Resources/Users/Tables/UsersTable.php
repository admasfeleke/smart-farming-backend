<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;


class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('region.name')
                    ->label('Region')
                    ->sortable()
                    ->toggleable(),

                \Filament\Tables\Columns\TextColumn::make('admin_level')
                    ->label('Level')
                    ->badge()
                    ->toggleable(),

                \Filament\Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('role')
                    ->relationship('role', 'name')
                    ->label('Role'),

                \Filament\Tables\Filters\SelectFilter::make('region')
                    ->relationship('region', 'name')
                    ->label('Region'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
