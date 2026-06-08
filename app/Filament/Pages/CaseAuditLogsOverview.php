<?php

namespace App\Filament\Pages;

use App\Models\CaseAuditLog;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class CaseAuditLogsOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Reports & Analytics';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.case-audit-logs-overview';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isSuperAdmin($user);
    }

    public function table(Table $table): Table
    {
        $query = CaseAuditLog::query()->with('actor');
        $user = auth()->user();

        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->can('viewAny', CaseAuditLog::class)) {
            $query->whereRaw('1 = 0');
        } elseif (! RegionScope::isSuperAdmin($user)) {
            $regions = RegionScope::accessibleRegionIds($user);
            if ($regions === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('actor_region_id', $regions);
            }
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Log #')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('entity_type')
                    ->badge()
                    ->label('Entity'),
                Tables\Columns\TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('action')
                    ->badge(),
                Tables\Columns\TextColumn::make('from_status')
                    ->label('From')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('to_status')
                    ->label('To')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('actor.name')
                    ->label('Actor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('actor_role')
                    ->label('Role')
                    ->badge(),
                Tables\Columns\TextColumn::make('actor_region_id')
                    ->label('Actor Region')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('note')
                    ->limit(56)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity_type')
                    ->options([
                        'disease_report' => 'Disease Report',
                        'alert' => 'Alert',
                    ]),
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'confirm' => 'confirm',
                        'reject' => 'reject',
                        'assign' => 'assign',
                        'acknowledge' => 'acknowledge',
                        'resolve' => 'resolve',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
