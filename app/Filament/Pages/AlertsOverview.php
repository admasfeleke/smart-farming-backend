<?php

namespace App\Filament\Pages;

use App\Models\Alert;
use App\Models\DiseaseReport;
use App\Models\User;
use App\Services\CaseAuditLogger;
use App\Support\RegionScope;
use App\Support\AuthorityMatrix;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\Action;
use UnitEnum;
use BackedEnum;

class AlertsOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | UnitEnum | null $navigationGroup = 'AI & Monitoring';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.alerts-overview';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isBackoffice($user)
            && AuthorityMatrix::can($user, 'alert.view_any');
    }

    public function table(Table $table): Table
    {
        $query = Alert::query()->with([
            'diseaseReport.crop',
            'diseaseReport.plot.farm.region',
        ]);

        $user = auth()->user();
        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->can('viewAny', Alert::class)) {
            $query->whereRaw('1 = 0');
        } elseif (! RegionScope::isSuperAdmin($user)) {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('diseaseReport.plot.farm', function ($q) use ($regionIds): void {
                    $q->whereIn('region_id', $regionIds);
                });
            }
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->limit(54),

                Tables\Columns\TextColumn::make('alert_type')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger'  => ['high', 'critical'],
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('diseaseReportFinding')
                    ->label('Finding')
                    ->state(fn (Alert $record): string => $record->diseaseReport instanceof DiseaseReport
                        ? $record->diseaseReport->backofficeFindingName()
                        : 'Awaiting analysis')
                    ->description(fn (Alert $record): string => $record->diseaseReport instanceof DiseaseReport
                        ? ucfirst($record->diseaseReport->backofficeFindingStage())
                        : 'Pending')
                    ->wrap()
                    ->limit(54),

                Tables\Columns\TextColumn::make('diseaseReport.crop.name')
                    ->label('Crop')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('diseaseReport.plot.plot_name')
                    ->label('Plot')
                    ->wrap()
                    ->limit(28)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('diseaseReport.plot.farm.farm_name')
                    ->label('Farm')
                    ->wrap()
                    ->limit(32),

                Tables\Columns\TextColumn::make('diseaseReport.plot.farm.region.name')
                    ->label('Administrative Unit')
                    ->wrap()
                    ->limit(32)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray'    => 'new',
                        'warning' => 'acknowledged',
                        'success' => 'resolved',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('triggered_at')
                    ->label('Triggered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->authorize(fn (Alert $record): bool => auth()->user()?->can('update', $record) === true)
                    ->visible(fn (Alert $record): bool => $record->status === 'open')
                    ->form([
                        Select::make('resolution_reason_code')
                            ->required()
                            ->label('Reason Code')
                            ->options([
                                'field_assigned' => 'Field assigned',
                                'under_investigation' => 'Under investigation',
                                'verification_started' => 'Verification started',
                            ]),
                        Textarea::make('resolution_comment')
                            ->required()
                            ->label('Comment')
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Alert $record, array $data): void {
                        $from = $record->status;
                        $record->status = 'acknowledged';
                        $record->acknowledged_by = auth()->id();
                        $record->acknowledged_at = now();
                        $record->last_action_by = auth()->id();
                        $record->last_action_at = now();
                        $record->resolution_reason_code = $data['resolution_reason_code'];
                        $record->resolution_comment = $data['resolution_comment'];
                        $record->save();

                        CaseAuditLogger::log(
                            'alert',
                            $record->id,
                            'acknowledge',
                            $from,
                            $record->status,
                            $data['resolution_comment'],
                            ['resolution_reason_code' => $data['resolution_reason_code']]
                        );

                        Notification::make()->success()->title('Alert acknowledged')->send();
                    }),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->authorize(fn (Alert $record): bool => auth()->user()?->can('update', $record) === true)
                    ->visible(fn (Alert $record): bool => in_array($record->status, ['open', 'acknowledged'], true))
                    ->form([
                        Select::make('resolution_reason_code')
                            ->required()
                            ->label('Reason Code')
                            ->options([
                                'treatment_applied' => 'Treatment applied',
                                'false_positive' => 'False positive',
                                'manual_resolution' => 'Manual resolution',
                            ]),
                        Textarea::make('resolution_comment')
                            ->required()
                            ->label('Comment')
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Alert $record, array $data): void {
                        $from = $record->status;
                        $record->status = 'resolved';
                        $record->resolved_by = auth()->id();
                        $record->resolved_at = now();
                        $record->last_action_by = auth()->id();
                        $record->last_action_at = now();
                        $record->resolution_reason_code = $data['resolution_reason_code'];
                        $record->resolution_comment = $data['resolution_comment'];
                        $record->save();

                        CaseAuditLogger::log(
                            'alert',
                            $record->id,
                            'resolve',
                            $from,
                            $record->status,
                            $data['resolution_comment'],
                            ['resolution_reason_code' => $data['resolution_reason_code']]
                        );

                        Notification::make()->success()->title('Alert resolved')->send();
                    }),
            ])
            ->defaultSort('triggered_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
