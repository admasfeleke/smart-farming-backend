<?php

namespace App\Filament\Pages;

use App\Models\CaseAssignment;
use App\Models\User;
use App\Support\RegionScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Actions\Action;
use UnitEnum;
use BackedEnum;

class SlaBreachesOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Reports & Analytics';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.sla-breaches-overview';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && in_array(RegionScope::roleName($user), ['super_admin', 'admin'], true);
    }

    public function table(Table $table): Table
    {
        /** @var EloquentBuilder<CaseAssignment> $query */
        $query = CaseAssignment::query()
            ->with([
                'diseaseReport.plot.farm.region',
                'diseaseReport.crop',
                'assignedTo',
                'assignedBy',
            ])
            ->where('status', 'active')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());

        $user = auth()->user();
        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->can('viewAny', CaseAssignment::class)) {
            $query->whereRaw('1 = 0');
        } elseif (! RegionScope::isSuperAdmin($user)) {
            $regions = RegionScope::accessibleRegionIds($user);
            if ($regions === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas(
                    'diseaseReport.plot.farm',
                    fn (EloquentBuilder $q): EloquentBuilder => $q->whereIn('region_id', $regions)
                );
            }
        }

        return $table
            ->query(fn (): EloquentBuilder => $query)
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
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To'),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Assigned At')
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
            ->defaultSort('due_at', 'asc')
            ->paginated([10, 25, 50]);
    }
}
