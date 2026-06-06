<?php

namespace App\Filament\Pages;

use App\Models\SoilHealth;
use App\Models\User;
use App\Services\CaseAuditLogger;
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
            ->query($query->with(['plot.farm.region', 'testedBy', 'reviewedBy']))
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('plot.farm.farm_name')
                    ->label('Farm')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('plot.plot_name')
                    ->label('Plot')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('plot.farm.region.name')
                    ->label('Region')
                    ->sortable(),

                Tables\Columns\TextColumn::make('testedBy.name')
                    ->label('Submitted By')
                    ->searchable()
                    ->toggleable(),

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
                    }),

                Tables\Columns\TextColumn::make('moisture_level')
                    ->label('Moisture')
                    ->formatStateUsing(fn ($state): string => $state !== null ? $state.'%' : '-')
                    ->sortable(),

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
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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
                                'supporter_confirmed' => 'Supporter confirmed',
                                'expert_confirmed' => 'Expert confirmed',
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

        return $query->whereHas('plot.farm', function (Builder $query) use ($regionIds): void {
            $query->whereIn('region_id', $regionIds);
        });
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
}
