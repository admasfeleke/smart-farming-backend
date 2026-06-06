<?php

namespace App\Filament\Pages;

use App\Models\CaseAssignment;
use App\Models\User;
use App\Support\RegionScope;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Actions\Action;
use UnitEnum;
use BackedEnum;

class MyAssignedCases extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'AI & Monitoring';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.my-assigned-cases';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && in_array(RegionScope::roleName($user), ['supporter', 'expert'], true);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $query = CaseAssignment::query()
            ->with([
                'diseaseReport.plot.farm.region',
                'diseaseReport.crop',
                'assignedBy',
            ])
            ->where('status', 'active');

        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');
        } else {
            if (! $user->can('viewAny', CaseAssignment::class)) {
                $query->whereRaw('1 = 0');
            }
            $query->where('assigned_to_user_id', $user->id);
            $regions = RegionScope::accessibleRegionIds($user);
            if ($regions === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
            }
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Case #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('diseaseReportFinding')
                    ->label('Finding')
                    ->state(fn (CaseAssignment $record): string => $record->diseaseReport?->backofficeFindingName() ?? 'Awaiting analysis')
                    ->description(fn (CaseAssignment $record): string => ucfirst($record->diseaseReport?->backofficeFindingStage() ?? 'pending'))
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('diseaseReport.crop.name')
                    ->label('Crop'),
                Tables\Columns\TextColumn::make('diseaseReport.plot.farm.farm_name')
                    ->label('Farm'),
                Tables\Columns\TextColumn::make('diseaseReport.plot.farm.region.name')
                    ->label('Region'),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'info' => 'normal',
                        'warning' => 'high',
                        'danger' => 'critical',
                    ]),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Due At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignedBy.name')
                    ->label('Assigned By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Assigned On')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('mark_completed')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->authorize(fn (CaseAssignment $record): bool => auth()->user()?->can('update', $record) === true)
                    ->requiresConfirmation()
                    ->action(function (CaseAssignment $record): void {
                        $record->status = 'completed';
                        $record->save();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
