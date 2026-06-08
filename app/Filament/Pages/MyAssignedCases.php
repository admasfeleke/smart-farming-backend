<?php

namespace App\Filament\Pages;

use App\Models\CaseAssignment;
use App\Models\User;
use App\Services\CaseAuditLogger;
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
                'diseaseReport.reporter',
                'soilHealth.plot.farm.region',
                'soilHealth.testedBy',
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
                $query->where(function ($query) use ($regions): void {
                    $query->whereHas('diseaseReport.plot.farm', fn ($q) => $q->whereIn('region_id', $regions))
                        ->orWhereHas('soilHealth.plot.farm', fn ($q) => $q->whereIn('region_id', $regions));
                });
            }
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Case #')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('case_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'soil_health' ? 'Soil' : 'Disease')
                    ->colors([
                        'success' => 'soil_health',
                        'info' => 'disease_report',
                    ]),
                Tables\Columns\TextColumn::make('caseFinding')
                    ->label('Finding')
                    ->state(fn (CaseAssignment $record): string => $this->caseFinding($record))
                    ->description(fn (CaseAssignment $record): string => $this->caseDescription($record))
                    ->wrap()
                    ->limit(64)
                    ->searchable(),
                Tables\Columns\TextColumn::make('cropOrMethod')
                    ->label('Crop / Method')
                    ->state(fn (CaseAssignment $record): string => $record->case_type === 'soil_health'
                        ? (string) ($record->soilHealth?->test_method ?? '-')
                        : (string) ($record->diseaseReport?->crop?->name ?? '-')),
                Tables\Columns\TextColumn::make('farmName')
                    ->label('Farm')
                    ->state(fn (CaseAssignment $record): string => $record->case_type === 'soil_health'
                        ? (string) ($record->soilHealth?->plot?->farm?->farm_name ?? '-')
                        : (string) ($record->diseaseReport?->plot?->farm?->farm_name ?? '-'))
                    ->wrap()
                    ->limit(36),
                Tables\Columns\TextColumn::make('regionName')
                    ->label('Region')
                    ->state(fn (CaseAssignment $record): string => $record->case_type === 'soil_health'
                        ? (string) ($record->soilHealth?->plot?->farm?->region?->name ?? '-')
                        : (string) ($record->diseaseReport?->plot?->farm?->region?->name ?? '-'))
                    ->wrap()
                    ->limit(36)
                    ->toggleable(),
                /*Tables\Columns\TextColumn::make('diseaseReport.crop.name')
                    ->label('Crop'),
                Tables\Columns\TextColumn::make('diseaseReport.plot.farm.farm_name')
                    ->label('Farm'),
                Tables\Columns\TextColumn::make('diseaseReport.plot.farm.region.name')
                    ->label('Region'),*/
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
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('assignedBy.name')
                    ->label('Assigned By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Assigned On')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('logCall')
                    ->label('Call Farmer')
                    ->icon('heroicon-o-phone')
                    ->color('gray')
                    ->visible(fn (CaseAssignment $record): bool => filled($this->farmerPhone($record)))
                    ->modalHeading(fn (CaseAssignment $record): string => 'Call '.$this->farmerName($record))
                    ->modalDescription(fn (CaseAssignment $record): string => 'Phone: '.$this->farmerPhone($record))
                    ->form([
                        \Filament\Forms\Components\Select::make('call_outcome')
                            ->label('Call outcome')
                            ->required()
                            ->options([
                                'called_reached' => 'Called and reached farmer',
                                'called_not_reached' => 'Called but not reached',
                                'requested_more_evidence' => 'Requested more evidence',
                                'field_visit_needed' => 'Field visit needed',
                            ]),
                        \Filament\Forms\Components\Textarea::make('call_note')
                            ->label('Call note')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (CaseAssignment $record, array $data): void {
                        $entityType = $record->case_type === 'soil_health' ? 'soil_health' : 'disease_report';
                        $entityId = $record->case_type === 'soil_health'
                            ? (int) $record->soil_health_id
                            : (int) $record->disease_report_id;
                        $status = $record->case_type === 'soil_health'
                            ? (string) $record->soilHealth?->review_status
                            : (string) $record->diseaseReport?->status;

                        CaseAuditLogger::log(
                            $entityType,
                            $entityId,
                            'farmer_call',
                            $status,
                            $status,
                            (string) $data['call_note'],
                            [
                                'call_outcome' => $data['call_outcome'],
                                'farmer_phone' => $this->farmerPhone($record),
                                'assignment_id' => $record->id,
                            ],
                        );
                    }),
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

    protected function caseFinding(CaseAssignment $record): string
    {
        if ($record->case_type === 'soil_health') {
            return 'Soil review: '.ucfirst((string) ($record->soilHealth?->review_status ?? 'pending'));
        }

        return $record->diseaseReport?->backofficeFindingName() ?? 'Awaiting analysis';
    }

    protected function caseDescription(CaseAssignment $record): string
    {
        if ($record->case_type === 'soil_health') {
            return trim(implode(' | ', array_filter([
                $record->soilHealth?->plot?->plot_name,
                $record->soilHealth?->test_date?->format('M d, Y'),
            ])));
        }

        return ucfirst($record->diseaseReport?->backofficeFindingStage() ?? 'pending');
    }

    protected function farmerName(CaseAssignment $record): string
    {
        return $record->case_type === 'soil_health'
            ? (string) ($record->soilHealth?->testedBy?->name ?? 'Farmer')
            : (string) ($record->diseaseReport?->reporter?->name ?? 'Farmer');
    }

    protected function farmerPhone(CaseAssignment $record): ?string
    {
        $phone = $record->case_type === 'soil_health'
            ? $record->soilHealth?->testedBy?->phone
            : $record->diseaseReport?->reporter?->phone;

        return filled($phone) ? (string) $phone : null;
    }
}
