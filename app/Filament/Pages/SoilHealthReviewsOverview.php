<?php

namespace App\Filament\Pages;

use App\Models\SoilHealth;
use App\Models\User;
use App\Services\CaseAuditLogger;
use App\Services\CaseAssignmentService;
use App\Support\AuthorityMatrix;
use App\Support\RegionScope;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;

class SoilHealthReviewsOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'AI & Monitoring';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.soil-health-reviews-overview';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && RegionScope::isBackoffice($user)
            && AuthorityMatrix::can($user, 'soil_health.view_any');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user instanceof User || ! AuthorityMatrix::can($user, 'soil_health.view_any')) {
            return null;
        }

        $count = self::scopedQueryFor($user)
            ->where('review_status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $query = $user instanceof User
            ? self::scopedQueryFor($user)
            : SoilHealth::query()->whereRaw('1 = 0');

        return $table
            ->query($query->with(['plot.farm.region', 'testedBy', 'reviewedBy', 'assignments']))
            ->columns([
                Tables\Columns\TextColumn::make('review_status')
                    ->label('Review')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'validated',
                        'danger' => 'rejected',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('test_date')
                    ->label('Test Date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('plot.farm.farm_name')
                    ->label('Farm')
                    ->searchable()
                    ->wrap()
                    ->limit(36),

                Tables\Columns\TextColumn::make('plot.plot_name')
                    ->label('Plot')
                    ->searchable()
                    ->wrap()
                    ->limit(32),

                Tables\Columns\TextColumn::make('plot.farm.region.name')
                    ->label('Administrative Unit')
                    ->sortable()
                    ->wrap()
                    ->limit(36),

                Tables\Columns\TextColumn::make('testedBy.name')
                    ->label('Submitted By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('test_method')
                    ->label('Method')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ph_level')
                    ->label('pH')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nutrients')
                    ->label('N / P / K')
                    ->state(function (SoilHealth $record): string {
                        return sprintf(
                            '%s / %s / %s',
                            $record->nitrogen ?? '-',
                            $record->phosphorus ?? '-',
                            $record->potassium ?? '-'
                        );
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('moisture_level')
                    ->label('Moisture')
                    ->formatStateUsing(fn ($state): string => $state !== null ? $state.'%' : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('evidence_url')
                    ->label('Evidence')
                    ->state(fn (SoilHealth $record): string => $record->evidence_url ? 'Available' : 'Missing')
                    ->badge()
                    ->colors([
                        'success' => 'Available',
                        'gray' => 'Missing',
                    ]),

                Tables\Columns\TextColumn::make('reviewedBy.name')
                    ->label('Reviewer')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('review_status')
                    ->label('Review Status')
                    ->options([
                        'pending' => 'Pending',
                        'validated' => 'Validated',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('test_method')
                    ->label('Test Method')
                    ->options(fn (): array => SoilHealth::query()
                        ->whereNotNull('test_method')
                        ->distinct()
                        ->orderBy('test_method')
                        ->pluck('test_method', 'test_method')
                        ->all()),
                Tables\Filters\Filter::make('test_date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('test_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('test_date', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('viewEvidence')
                    ->label('Evidence')
                    ->icon('heroicon-o-photo')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (SoilHealth $record) => view('filament.pages.partials.soil-health-evidence', [
                        'record' => $record,
                        'items' => $this->evidenceItems($record),
                    ])),

                Action::make('assign')
                    ->label(function (): string {
                        $user = auth()->user();

                        return $user instanceof User && RegionScope::roleName($user) === 'supporter'
                            ? 'Refer to Subject Matter Specialist'
                            : 'Assign';
                    })
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->authorize(fn (SoilHealth $record): bool => $this->canAssignSoilRecord($record))
                    ->visible(fn (SoilHealth $record): bool => $this->canAssignSoilRecord($record))
                    ->form(fn (SoilHealth $record): array => [
                        Select::make('assigned_to_user_id')
                            ->label('Assign to Officer')
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
                        Textarea::make('assignment_note')
                            ->label('Assignment Note')
                            ->maxLength(1000),
                    ])
                    ->action(function (SoilHealth $record, array $data): void {
                        $assignee = User::query()->findOrFail((int) $data['assigned_to_user_id']);
                        app(CaseAssignmentService::class)->assignSoilHealth(
                            $record,
                            $assignee,
                            auth()->user(),
                            (string) $data['priority'],
                            $data['assignment_note'] ?? null,
                        );

                        Notification::make()->success()->title('Soil case assigned')->send();
                    }),

                Action::make('logCall')
                    ->label('Call Farmer')
                    ->icon('heroicon-o-phone')
                    ->color('gray')
                    ->authorize(fn (SoilHealth $record): bool => auth()->user()?->can('view', $record) === true)
                    ->visible(fn (SoilHealth $record): bool => filled($record->testedBy?->phone))
                    ->modalHeading(fn (SoilHealth $record): string => 'Call '.$record->testedBy?->name)
                    ->modalDescription(fn (SoilHealth $record): string => 'Phone: '.($record->testedBy?->phone ?? '-'))
                    ->form([
                        Select::make('call_outcome')
                            ->label('Call outcome')
                            ->required()
                            ->options([
                                'called_reached' => 'Called and reached farmer',
                                'called_not_reached' => 'Called but not reached',
                                'requested_retest' => 'Requested soil retest',
                                'field_visit_needed' => 'Field visit needed',
                            ]),
                        Textarea::make('call_note')
                            ->label('Call note')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (SoilHealth $record, array $data): void {
                        CaseAuditLogger::log(
                            'soil_health',
                            $record->id,
                            'farmer_call',
                            (string) $record->review_status,
                            (string) $record->review_status,
                            (string) $data['call_note'],
                            [
                                'call_outcome' => $data['call_outcome'],
                                'farmer_user_id' => $record->tested_by,
                                'farmer_phone' => $record->testedBy?->phone,
                            ],
                        );

                        Notification::make()->success()->title('Call note recorded')->send();
                    }),

                Action::make('validate')
                    ->label('Validate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->authorize(fn (SoilHealth $record): bool => auth()->user()?->can('verify', $record) === true)
                    ->visible(fn (SoilHealth $record): bool => strtolower((string) $record->review_status) !== 'validated')
                    ->form([
                        Select::make('review_reason_code')
                            ->label('Reason')
                            ->required()
                            ->options([
                                'evidence_consistent' => 'Evidence is consistent',
                                'field_measurement_verified' => 'Field measurement verified',
                                'supporter_confirmed' => 'Development Agent confirmed',
                                'expert_confirmed' => 'Subject Matter Specialist confirmed',
                            ]),
                        Textarea::make('review_comment')
                            ->label('Reviewer Comment')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (SoilHealth $record, array $data): void {
                        $this->setReviewDecision($record, 'validated', $data);
                        Notification::make()->success()->title('Soil health record validated')->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->authorize(fn (SoilHealth $record): bool => auth()->user()?->can('verify', $record) === true)
                    ->visible(fn (SoilHealth $record): bool => strtolower((string) $record->review_status) !== 'rejected')
                    ->form([
                        Select::make('review_reason_code')
                            ->label('Reason')
                            ->required()
                            ->options([
                                'insufficient_evidence' => 'Insufficient evidence',
                                'measurement_outlier' => 'Measurement appears inconsistent',
                                'wrong_plot_context' => 'Wrong plot context',
                                'needs_retest' => 'Needs retest',
                            ]),
                        Textarea::make('review_comment')
                            ->label('Reviewer Comment')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (SoilHealth $record, array $data): void {
                        $this->setReviewDecision($record, 'rejected', $data);
                        Notification::make()->success()->title('Soil health record rejected')->send();
                    }),

                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->authorize(fn (SoilHealth $record): bool => auth()->user()?->can('verify', $record) === true)
                    ->visible(fn (SoilHealth $record): bool => strtolower((string) $record->review_status) !== 'pending')
                    ->form([
                        Textarea::make('review_comment')
                            ->label('Reason for reopening')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (SoilHealth $record, array $data): void {
                        $from = (string) $record->review_status;
                        $note = (string) $data['review_comment'];

                        $record->forceFill([
                            'review_status' => 'pending',
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                            'review_reason_code' => null,
                            'review_comment' => $note,
                        ])->save();

                        CaseAuditLogger::log(
                            'soil_health',
                            $record->id,
                            'reopen',
                            $from,
                            'pending',
                            $note,
                        );

                        Notification::make()->success()->title('Soil health record reopened')->send();
                    }),
            ])
            ->defaultSort('test_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected static function scopedQueryFor(User $user): Builder
    {
        $query = SoilHealth::query();

        if (! $user->can('viewAny', SoilHealth::class)) {
            return $query->whereRaw('1 = 0');
        }

        if (RegionScope::isSuperAdmin($user)) {
            return $query;
        }

        $regionIds = RegionScope::accessibleRegionIds($user);
        if ($regionIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereHas('plot.farm', function (Builder $query) use ($regionIds): void {
            $query->whereIn('region_id', $regionIds);
        });

        if (in_array(RegionScope::roleName($user), ['supporter', 'expert'], true)) {
            $query->whereHas('assignments', function (Builder $q) use ($user): void {
                $q->where('case_type', 'soil_health')
                    ->where('assigned_to_user_id', $user->id)
                    ->where('status', 'active');
            });
        }

        return $query;
    }

    protected function setReviewDecision(SoilHealth $record, string $status, array $data): void
    {
        $from = (string) $record->review_status;
        $comment = (string) ($data['review_comment'] ?? '');
        $reason = (string) ($data['review_reason_code'] ?? '');

        $record->forceFill([
            'review_status' => $status,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_reason_code' => $reason,
            'review_comment' => $comment,
        ])->save();

        CaseAuditLogger::log(
            'soil_health',
            $record->id,
            $status === 'validated' ? 'validate' : 'reject',
            $from,
            $status,
            $comment,
            ['review_reason_code' => $reason],
        );
    }

    protected function evidenceItems(SoilHealth $record): array
    {
        if (! $record->evidence_url) {
            return [];
        }

        $type = strtolower((string) $record->evidence_type);
        $url = (string) $record->evidence_url;
        $isImage = str_contains($type, 'image')
            || str_ends_with(strtolower($url), '.jpg')
            || str_ends_with(strtolower($url), '.jpeg')
            || str_ends_with(strtolower($url), '.png')
            || str_ends_with(strtolower($url), '.webp');

        return [[
            'label' => 'Submitted soil evidence',
            'url' => $url,
            'type' => $record->evidence_type,
            'is_image' => $isImage,
        ]];
    }

    protected function canAssignSoilRecord(SoilHealth $record): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
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
                fn ($assignment): bool =>
                    $assignment->status === 'active'
                    && (int) $assignment->assigned_to_user_id === (int) $user->id
            );
        }

        return true;
    }
}
