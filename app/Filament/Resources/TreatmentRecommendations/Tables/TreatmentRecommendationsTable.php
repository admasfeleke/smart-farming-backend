<?php

namespace App\Filament\Resources\TreatmentRecommendations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TreatmentRecommendationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(56),

                TextColumn::make('crop.name')
                    ->label('Crop')
                    ->sortable()
                    ->placeholder('General'),

                TextColumn::make('disease_key')
                    ->label('Disease')
                    ->searchable()
                    ->placeholder('Keyword/default')
                    ->wrap()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('disease_keyword')
                    ->label('Keyword')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('recommendation_type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('pesticideProduct.active_ingredient')
                    ->label('Active ingredient')
                    ->wrap()
                    ->limit(42)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('crop_id')
                    ->label('Crop')
                    ->relationship('crop', 'name'),

                SelectFilter::make('approval_status')
                    ->options([
                        'approved' => 'Approved',
                        'draft' => 'Draft',
                        'retired' => 'Retired',
                    ]),

                SelectFilter::make('recommendation_type')
                    ->options([
                        'chemical' => 'Chemical',
                        'natural' => 'Natural',
                        'integrated' => 'Integrated',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => in_array(auth()->user()?->role?->name, ['super_admin', 'admin'], true)
                        && $record->approval_status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->forceFill([
                            'approval_status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                            'is_active' => true,
                        ])->save();
                    }),
                Action::make('retire')
                    ->label('Retire')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn ($record): bool => in_array(auth()->user()?->role?->name, ['super_admin', 'admin'], true)
                        && $record->approval_status !== 'retired')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->forceFill([
                            'approval_status' => 'retired',
                            'is_active' => false,
                        ])->save();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
