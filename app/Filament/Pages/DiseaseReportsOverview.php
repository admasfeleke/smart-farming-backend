<?php

namespace App\Filament\Pages;

use App\Models\CaseAssignment;
use App\Models\DiseaseReport;
use App\Models\User;
use App\Services\CaseAuditLogger;
use App\Services\CaseAssignmentService;
use App\Services\DiseaseReportReviewService;
use App\Support\RegionScope;
use App\Support\AuthorityMatrix;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use UnitEnum;
use BackedEnum;

class DiseaseReportsOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'AI & Monitoring';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.disease-reports-overview';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user instanceof User
            && RegionScope::isBackoffice($user)
            && AuthorityMatrix::can($user, 'disease_report.view_any');
    }

    /**
     * @return array<string, int|string>
     */
    public function overviewStats(): array
    {
        $query = $this->scopedReportsQuery();
        $total = (clone $query)->count();
        $reviewing = (clone $query)->whereIn('status', ['new', 'reviewing', 'processing'])->count();
        $highRisk = (clone $query)->whereIn('severity', ['high', 'critical'])->count();
        $withImages = (clone $query)->whereNotNull('image_path')->count();
        $aiEvidence = (clone $query)
            ->where(function (Builder $q): void {
                $q->whereNotNull('confidence_score');
                if (Schema::hasColumn('disease_reports', 'scan_metadata')) {
                    $q->orWhereNotNull('scan_metadata->server_inference_top_scores')
                        ->orWhereNotNull('scan_metadata->server_inference_disease_name');
                }
            })
            ->count();
        $reviewed = (clone $query)
            ->where(function (Builder $q): void {
                $q->whereIn('status', ['confirmed', 'rejected'])
                    ->orWhereNotNull('reviewed_at')
                    ->orWhereNotNull('verified_at');
            })
            ->count();

        return [
            'total' => $total,
            'reviewing' => $reviewing,
            'highRisk' => $highRisk,
            'withImages' => $withImages,
            'aiEvidence' => $aiEvidence,
            'reviewed' => $reviewed,
            'imageCoverage' => $this->percent($withImages, $total),
            'aiCoverage' => $this->percent($aiEvidence, $total),
            'reviewCompletion' => $this->percent($reviewed, $total),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, DiseaseReport>
     */
    public function triageCards()
    {
        return $this->scopedReportsQuery()
            ->with([
                'plot.farm.region',
                'crop',
                'reporter',
                'evidence',
                'assignments.assignedTo',
                'assignments.assignedBy',
                'latestFailedInference',
            ])
            ->where(function (Builder $q): void {
                $q->whereIn('status', ['new', 'reviewing', 'processing'])
                    ->orWhereIn('severity', ['high', 'critical']);
            })
            ->orderByRaw("CASE WHEN severity IN ('critical', 'high') THEN 0 ELSE 1 END")
            ->orderByDesc('reported_at')
            ->limit(8)
            ->get();
    }

    public function table(Table $table): Table
    {
        $query = $this->scopedReportsQuery()
            ->with([
                'plot.farm.region',
                'crop',
                'reporter',
                'evidence',
                'assignments.assignedTo',
                'assignments.assignedBy',
                'latestFailedInference',
            ]);

        return $table
            ->query($query)
            ->striped()
            ->columns([
                Tables\Columns\ImageColumn::make('photo_preview')
                    ->label('')
                    ->getStateUsing(fn (DiseaseReport $record): ?string => $record->backofficeOriginalImageSrc())
                    ->square()
                    ->width(52)
                    ->height(52),

                Tables\Columns\TextColumn::make('finding')
                    ->label('Finding')
                    ->state(fn (DiseaseReport $record): string => $record->backofficeFindingName())
                    ->description(fn (DiseaseReport $record): string => trim(implode(' | ', array_filter([
                        ucfirst($record->backofficeFindingStage()),
                        $record->crop?->name,
                        $record->plot?->farm?->farm_name,
                    ]))))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('disease_name', 'like', "%{$search}%")
                                ->orWhere('scan_metadata->server_inference_disease_name', 'like', "%{$search}%")
                                ->orWhere('scan_metadata->offline_local_disease_name', 'like', "%{$search}%")
                                ->orWhere('scan_metadata->verified_disease_name', 'like', "%{$search}%");
                        });
                    })
                    ->wrap()
                    ->limit(64),

                Tables\Columns\TextColumn::make('severity')
                    ->label('Risk')
                    ->badge()
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => ['high', 'critical'],
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('confidence_score')
                    ->label('Confidence')
                    ->state(fn (DiseaseReport $record): ?float => $record->backofficeFindingConfidence())
                    ->formatStateUsing(fn ($state) => $state !== null ? round($state * 100, 1).'%' : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('review_status')
                    ->label('Review')
                    ->state(fn (DiseaseReport $record): string => trim(implode(' / ', array_filter([
                        ucfirst((string) $record->status),
                        ucfirst($record->backofficeFindingStage()),
                    ]))))
                    ->badge()
                    ->colors([
                        'success' => fn (string $state): bool => str_contains(strtolower($state), 'confirmed'),
                        'danger' => fn (string $state): bool => str_contains(strtolower($state), 'rejected'),
                        'warning' => fn (string $state): bool => str_contains(strtolower($state), 'reviewing'),
                        'info' => fn (string $state): bool => str_contains(strtolower($state), 'processing'),
                        'gray' => fn (string $state): bool => str_contains(strtolower($state), 'new'),
                    ]),

                Tables\Columns\TextColumn::make('plot.farm.region.name')
                    ->label('Location')
                    ->description(fn (DiseaseReport $record): string => $record->plot?->plot_name
                        ? 'Plot: '.$record->plot->plot_name
                        : '')
                    ->wrap()
                    ->limit(42),

                Tables\Columns\TextColumn::make('ai_evidence')
                    ->label('Evidence')
                    ->state(fn (DiseaseReport $record): string => $this->evidenceSummary($record))
                    ->badge()
                    ->colors([
                        'info' => fn (string $state): bool => str_contains($state, 'AI'),
                        'success' => fn (string $state): bool => str_contains($state, 'Image'),
                        'gray' => 'No evidence',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reported_at')
                    ->label('Reported')
                    ->since()
                    ->description(fn (DiseaseReport $record): string => optional($record->reported_at)->format('M d, Y H:i') ?? '-')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Review status')
                    ->options([
                        'new' => 'New',
                        'reviewing' => 'Reviewing',
                        'processing' => 'Processing',
                        'confirmed' => 'Confirmed',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),
                Tables\Filters\SelectFilter::make('image_path')
                    ->label('Image evidence')
                    ->options([
                        'has_image' => 'Has image',
                        'no_image' => 'No image',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === 'has_image') {
                            return $query->whereNotNull('image_path');
                        }

                        if ($value === 'no_image') {
                            return $query->whereNull('image_path');
                        }

                        return $query;
                    }),
            ])
            ->recordAction('viewDetails')
            ->actions([
                Action::make('viewDetails')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (DiseaseReport $record): string => 'Disease Report #'.$record->id)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('5xl')
                    ->modalContent(fn (DiseaseReport $record) => view('filament.pages.partials.disease-report-details', [
                        'record' => $record,
                        'items' => $this->evidenceItems($record),
                    ])),
                Action::make('viewEvidence')
                    ->label('View Evidence')
                    ->icon('heroicon-o-photo')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->fillForm(fn (DiseaseReport $record): array => [])
                    ->modalContent(fn (DiseaseReport $record) => view('filament.pages.partials.disease-report-evidence', [
                        'items' => $this->evidenceItems($record),
                    ])),
                Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->authorize(fn (DiseaseReport $record): bool => auth()->user()?->can('verify', $record) === true)
                    ->visible(fn (DiseaseReport $record): bool => in_array($record->status, ['new', 'reviewing', 'processing'], true))
                    ->form([
                        TextInput::make('disease_name')
                            ->label('Confirmed disease')
                            ->required()
                            ->maxLength(100)
                            ->default(fn (DiseaseReport $record): string => $this->defaultReviewDiseaseName($record)),
                        Select::make('severity')
                            ->required()
                            ->default(fn (DiseaseReport $record): string => (string) ($record->severity ?: 'medium'))
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ]),
                        TextInput::make('confidence_score')
                            ->label('Confidence score')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->default(fn (DiseaseReport $record): ?float => $record->backofficeFindingConfidence()),
                        Select::make('decision_reason_code')
                            ->required()
                            ->options([
                                'visual_match' => 'Visual match',
                                'expert_confirmed' => 'Expert confirmed',
                                'field_pattern_match' => 'Field pattern match',
                            ]),
                        Textarea::make('decision_comment')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (DiseaseReport $record, array $data): void {
                        app(DiseaseReportReviewService::class)->confirm(
                            $record,
                            auth()->user(),
                            (string) $data['disease_name'],
                            (string) $data['severity'],
                            isset($data['confidence_score']) && $data['confidence_score'] !== ''
                                ? (float) $data['confidence_score']
                                : null,
                            (string) $data['decision_reason_code'],
                            $data['decision_comment'] ?? null,
                        );

                        Notification::make()->success()->title('Report confirmed')->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->authorize(fn (DiseaseReport $record): bool => auth()->user()?->can('verify', $record) === true)
                    ->visible(fn (DiseaseReport $record): bool => in_array($record->status, ['new', 'reviewing', 'processing'], true))
                    ->form([
                        Select::make('decision_reason_code')
                            ->required()
                            ->options([
                                'insufficient_evidence' => 'Insufficient evidence',
                                'image_quality_issue' => 'Image quality issue',
                                'wrong_crop_context' => 'Wrong crop context',
                            ]),
                        Textarea::make('decision_comment')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (DiseaseReport $record, array $data): void {
                        app(DiseaseReportReviewService::class)->reject(
                            $record,
                            auth()->user(),
                            (string) $data['decision_reason_code'],
                            $data['decision_comment'] ?? null,
                        );

                        Notification::make()->success()->title('Report rejected')->send();
                    }),
                Action::make('assign')
                    ->label(function (): string {
                        $user = auth()->user();

                        return $user instanceof User && RegionScope::roleName($user) === 'supporter'
                            ? 'Escalate to Expert'
                            : 'Assign';
                    })
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->authorize(fn (DiseaseReport $record): bool => $this->canAssignReport($record))
                    ->visible(fn (DiseaseReport $record): bool => $this->canAssignReport($record))
                    ->form(fn (DiseaseReport $record): array => [
                        Select::make('assigned_to_user_id')
                            ->label('Assign To')
                            ->searchable()
                            ->required()
                            ->options(function () use ($record) {
                                $user = auth()->user();
                                if (! $user instanceof User) {
                                    return [];
                                }

                                $roles = RegionScope::roleName($user) === 'supporter'
                                    ? ['expert']
                                    : ['supporter', 'expert'];

                                return app(CaseAssignmentService::class)->assignableReviewerOptions(
                                    $user,
                                    $record->plot?->farm?->region_id,
                                    $roles,
                                );
                            }),
                        Select::make('priority')
                            ->required()
                            ->default('normal')
                            ->options([
                                'low' => 'Low',
                                'normal' => 'Normal',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ]),
                        Textarea::make('decision_comment')
                            ->label('Assignment Note')
                            ->maxLength(1000),
                    ])
                    ->action(function (DiseaseReport $record, array $data): void {
                        $assignee = User::query()->findOrFail((int) $data['assigned_to_user_id']);
                        app(CaseAssignmentService::class)->assignDiseaseReport(
                            $record,
                            $assignee,
                            auth()->user(),
                            (string) $data['priority'],
                            $data['decision_comment'] ?? null,
                        );

                        Notification::make()->success()->title('Case assigned')->send();
                    }),
                Action::make('logCall')
                    ->label('Call Farmer')
                    ->icon('heroicon-o-phone')
                    ->color('gray')
                    ->authorize(fn (DiseaseReport $record): bool => auth()->user()?->can('view', $record) === true)
                    ->visible(fn (DiseaseReport $record): bool => filled($record->reporter?->phone))
                    ->modalHeading(fn (DiseaseReport $record): string => 'Call '.$record->reporter?->name)
                    ->modalDescription(fn (DiseaseReport $record): string => 'Phone: '.($record->reporter?->phone ?? '-'))
                    ->form([
                        Select::make('call_outcome')
                            ->label('Call outcome')
                            ->required()
                            ->options([
                                'called_reached' => 'Called and reached farmer',
                                'called_not_reached' => 'Called but not reached',
                                'requested_more_evidence' => 'Requested more evidence',
                                'field_visit_needed' => 'Field visit needed',
                            ]),
                        Textarea::make('call_note')
                            ->label('Call note')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (DiseaseReport $record, array $data): void {
                        CaseAuditLogger::log(
                            'disease_report',
                            $record->id,
                            'farmer_call',
                            $record->status,
                            $record->status,
                            (string) $data['call_note'],
                            [
                                'call_outcome' => $data['call_outcome'],
                                'farmer_user_id' => $record->reported_by,
                                'farmer_phone' => $record->reporter?->phone,
                            ],
                        );

                        Notification::make()->success()->title('Call note recorded')->send();
                    }),
            ])

            ->defaultSort('reported_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function scopedReportsQuery(): Builder
    {
        $query = DiseaseReport::query();

        $user = auth()->user();
        $role = $user instanceof User ? RegionScope::roleName($user) : null;
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }
        if (! $user->can('viewAny', DiseaseReport::class)) {
            return $query->whereRaw('1 = 0');
        }
        if (in_array($role, ['supporter', 'expert'], true)) {
            return $query->whereHas('assignments', function (Builder $q) use ($user): void {
                $q->where('assigned_to_user_id', $user->id)
                    ->where('status', 'active');
            });
        }
        if (! RegionScope::isSuperAdmin($user)) {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                return $query->whereRaw('1 = 0');
            }
            $query->whereHas('plot.farm', function ($q) use ($regionIds): void {
                $q->whereIn('region_id', $regionIds);
            });
        }

        return $query;
    }

    protected function evidenceSummary(DiseaseReport $record): string
    {
        $hasImage = filled($record->image_path);
        $hasAiScores = count($record->backofficeInferenceTopScores()) > 0;

        return match (true) {
            $hasImage && $hasAiScores => 'Image + AI',
            $hasImage => 'Image',
            $hasAiScores => 'AI only',
            default => 'No evidence',
        };
    }

    protected function defaultReviewDiseaseName(DiseaseReport $record): string
    {
        $name = trim($record->backofficeFindingName());

        return strtolower($name) === 'awaiting analysis' ? '' : $name;
    }

    public function cardTone(DiseaseReport $record): string
    {
        $severity = strtolower((string) $record->severity);

        return match ($severity) {
            'critical', 'high' => 'danger',
            'medium' => 'warning',
            default => 'normal',
        };
    }

    protected function percent(int $value, int $total): string
    {
        if ($total <= 0) {
            return '0%';
        }

        return round(($value / $total) * 100).'%';
    }

    protected function evidenceItems(DiseaseReport $record): array
    {
        $items = [];
        $originalSrc = $record->backofficeOriginalImageSrc();
        if ($originalSrc !== null) {
            $items[] = [
                'label' => 'Original capture',
                'url' => $originalSrc,
            ];
        }
        foreach ($record->evidence as $item) {
            $previewSrc = $item->backofficePreviewSrc();
            if ($previewSrc === null) {
                continue;
            }
            $items[] = [
                'label' => ucwords(str_replace('_', ' ', (string) $item->kind)),
                'url' => $previewSrc,
            ];
        }

        return $items;
    }

    protected function canAssignReport(DiseaseReport $record): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        $status = strtolower(trim((string) $record->status));
        if (! in_array($status, ['new', 'reviewing', 'processing'], true)) {
            return false;
        }

        $role = RegionScope::roleName($user);
        if (! in_array($role, ['super_admin', 'admin', 'supporter'], true)) {
            return false;
        }

        if (! RegionScope::canAccessRegion($user, $record->plot?->farm?->region_id)) {
            return false;
        }

        if ($role === 'supporter') {
            return $record->assignments->contains(
                fn (CaseAssignment $assignment): bool =>
                    $assignment->status === 'active'
                    && (int) $assignment->assigned_to_user_id === (int) $user->id
            );
        }

        return ! $record->assignments->contains(
            fn (CaseAssignment $assignment): bool => $assignment->status === 'active'
        );
    }
}
