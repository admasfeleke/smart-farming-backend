<?php

use App\Models\Alert;
use App\Models\CaseAssignment;
use App\Models\DiseaseReport;
use App\Models\Role;
use App\Models\User;
use App\Services\AlertService;
use App\Services\InferencePipelineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('alerts:reconcile {--dry-run : Show changes without updating records}', function (AlertService $alertService) {
    $dryRun = (bool) $this->option('dry-run');
    $now = now();

    $invalidAlerts = Alert::query()
        ->whereIn('status', ['open', 'acknowledged'])
        ->whereHas('diseaseReport', function ($query) {
            $query->where(function ($subQuery) {
                $subQuery
                    ->where('status', '!=', 'confirmed')
                    ->orWhereNotIn('severity', ['high', 'critical']);
            });
        })
        ->get();

    if (! $dryRun) {
        foreach ($invalidAlerts as $alert) {
            $alert->update([
                'status' => 'resolved',
                'resolved_at' => $now,
            ]);
        }
    }

    $eligibleReports = DiseaseReport::query()
        ->where('status', 'confirmed')
        ->whereIn('severity', ['high', 'critical'])
        ->whereDoesntHave('alerts', function ($query) {
            $query->whereIn('status', ['open', 'acknowledged']);
        })
        ->get();

    if (! $dryRun) {
        foreach ($eligibleReports as $report) {
            $alertService->handleDiseaseReport($report);
        }
    }

    $this->info(sprintf(
        'alerts:reconcile complete (dry_run=%s) invalid_alerts=%d eligible_reports=%d',
        $dryRun ? 'true' : 'false',
        $invalidAlerts->count(),
        $eligibleReports->count()
    ));
})->purpose('Reconcile alert records against current disease report rules');

Artisan::command('ops:health-check {--json : Output JSON payload}', function (InferencePipelineService $inference) {
    $checks = [];

    $check = function (string $name, bool $ok, string $message, string $severity = 'critical') use (&$checks): void {
        $checks[] = [
            'name' => $name,
            'ok' => $ok,
            'message' => $message,
            'severity' => $severity,
        ];
    };

    try {
        DB::select('SELECT 1');
        $check('database', true, 'Database connection is healthy.');
    } catch (\Throwable $e) {
        $check('database', false, 'Database check failed: '.$e->getMessage());
    }

    $logDir = storage_path('logs');
    $logFile = storage_path('logs/laravel.log');
    $storageSeverity = app()->environment('production') ? 'critical' : 'warning';
    $logDefault = strtolower((string) config('logging.default', 'stack'));
    $stackChannelsConfig = data_get(config('logging.channels'), 'stack.channels', []);
    $stackChannelsInput = is_array($stackChannelsConfig)
        ? $stackChannelsConfig
        : explode(',', (string) $stackChannelsConfig);
    $stackChannels = array_values(array_filter(array_map(
        static fn (string $item): string => trim(strtolower($item)),
        $stackChannelsInput
    )));
    $fileBasedChannels = ['single', 'daily'];
    $usesFileLogging = in_array($logDefault, $fileBasedChannels, true)
        || ($logDefault === 'stack' && array_intersect($stackChannels, $fileBasedChannels) !== []);

    if ($usesFileLogging) {
        $check('storage.logs_dir_writable', File::isDirectory($logDir) && File::isWritable($logDir), 'storage/logs directory must be writable.', $storageSeverity);
        $check('storage.laravel_log_writable', (File::exists($logFile) && File::isWritable($logFile)) || (! File::exists($logFile) && File::isWritable($logDir)), 'storage/logs/laravel.log must be writable.', $storageSeverity);
    } else {
        $check('storage.logs_dir_writable', true, "File-based logging disabled on channel '{$logDefault}'; writability check skipped.", 'warning');
        $check('storage.laravel_log_writable', true, "File-based logging disabled on channel '{$logDefault}'; file check skipped.", 'warning');
    }

    $pestTemp = base_path('vendor/pestphp/pest/.temp');
    $check(
        'dev.pest_temp_writable',
        ! File::isDirectory($pestTemp) || File::isWritable($pestTemp),
        'Pest temp directory should be writable for local test runs.',
        'warning'
    );

    $queueConnection = (string) config('queue.default', '');
    $check('queue.connection', $queueConnection !== '', 'Queue default connection must be configured.');
    $check('queue.not_sync', strtolower($queueConnection) !== 'sync', 'Queue connection should not be sync in production.', 'warning');
    $check('queue.failed_jobs_table', Schema::hasTable('failed_jobs'), 'failed_jobs table should exist for retry/dead-letter operations.', 'warning');

    if (Schema::hasTable('jobs')) {
        $queuedJobs = (int) DB::table('jobs')->count();
        $check('queue.jobs_backlog', $queuedJobs < 2000, "Queued jobs backlog count: {$queuedJobs}.", $queuedJobs >= 2000 ? 'critical' : 'warning');
    }

    if (Schema::hasTable('failed_jobs')) {
        $failedJobs = (int) DB::table('failed_jobs')->count();
        $check('queue.failed_jobs_backlog', $failedJobs < 200, "Failed jobs count: {$failedJobs}.", $failedJobs >= 200 ? 'critical' : 'warning');
    }

    $openCriticalAlerts = Alert::query()
        ->where('status', 'open')
        ->whereIn('severity', ['high', 'critical'])
        ->count();
    $check(
        'alerts.open_critical',
        $openCriticalAlerts < 200,
        "Open high/critical alerts: {$openCriticalAlerts}.",
        $openCriticalAlerts >= 200 ? 'critical' : 'warning'
    );

    if (Schema::hasTable('case_assignments')) {
        $overdueAssignments = CaseAssignment::query()
            ->where('status', 'active')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();
        $check(
            'assignments.overdue_active',
            $overdueAssignments < 200,
            "Overdue active assignments: {$overdueAssignments}.",
            $overdueAssignments >= 200 ? 'critical' : 'warning'
        );
    }

    $inferenceEnabled = (bool) config('services.inference.enabled', false);
    $inferenceReport = $inference->healthReport();
    $inferenceHealthy = (bool) ($inferenceReport['healthy'] ?? false);
    $inferenceSeverity = app()->environment('production') && $inferenceEnabled ? 'critical' : 'warning';
    $serviceStatus = (string) ($inferenceReport['service_status'] ?? 'unknown');
    $contractMessages = (array) ($inferenceReport['contract_messages'] ?? []);
    $inferenceErrors = (array) ($inferenceReport['errors'] ?? []);
    $inferenceMessage = 'Inference disabled by configuration.';
    if ($inferenceEnabled) {
        $details = [];
        if ($contractMessages !== []) {
            $details[] = implode('; ', $contractMessages);
        }
        if ($inferenceErrors !== []) {
            $details[] = implode('; ', $inferenceErrors);
        }
        $suffix = $details === [] ? '' : ' Details: '.implode(' | ', $details);
        $inferenceMessage = "Inference health check executed (status={$serviceStatus}).{$suffix}";
    }
    $check(
        'inference.service',
        $inferenceHealthy,
        $inferenceMessage,
        $inferenceSeverity
    );

    $reviewOnlyMode = (bool) config('services.inference.review_only_mode', false);
    $check(
        'inference.review_only_mode',
        ! $reviewOnlyMode,
        $reviewOnlyMode
            ? 'Inference review-only mode is enabled; automated treatment remains blocked.'
            : 'Inference review-only mode is disabled.',
        'warning'
    );

    if (Schema::hasTable('disease_reports')) {
        $windowDays = (int) config('services.inference.kpi_window_days', 7);
        $windowDays = max(1, $windowDays);
        $since = now()->subDays($windowDays);
        $recentAiBase = DiseaseReport::query()
            ->where('report_source', 'ai')
            ->where('reported_at', '>=', $since);
        $recentAiCount = (int) (clone $recentAiBase)->count();
        $kpiSeverity = app()->environment('production') && $inferenceEnabled ? 'critical' : 'warning';

        if ($recentAiCount === 0) {
            $check(
                'inference.kpi_window_volume',
                true,
                "No AI reports in last {$windowDays} days; KPI rate checks skipped.",
                'warning'
            );
        } else {
            $uncertainCount = (int) (clone $recentAiBase)
                ->where(function ($query) {
                    $query
                        ->where('description', 'like', '%marked uncertain%')
                        ->orWhere('description', 'like', '%low confidence prediction%')
                        ->orWhere('description', 'like', '%review-only mode%');
                })
                ->count();
            $familyMismatchCount = (int) (clone $recentAiBase)
                ->where('description', 'like', '%does not match selected crop%')
                ->count();
            $uncertainRate = $uncertainCount / max($recentAiCount, 1);
            $familyMismatchRate = $familyMismatchCount / max($recentAiCount, 1);

            $maxUncertainRate = (float) config('services.inference.max_uncertain_rate', 0.25);
            $maxFamilyMismatchRate = (float) config('services.inference.max_family_mismatch_rate', 0.10);
            $check(
                'inference.kpi_uncertain_rate',
                $uncertainRate <= $maxUncertainRate,
                sprintf(
                    'AI uncertain rate last %d days: %.4f (threshold %.4f, reports=%d).',
                    $windowDays,
                    $uncertainRate,
                    $maxUncertainRate,
                    $recentAiCount
                ),
                $kpiSeverity
            );
            $check(
                'inference.kpi_family_mismatch_rate',
                $familyMismatchRate <= $maxFamilyMismatchRate,
                sprintf(
                    'AI crop-family mismatch rate last %d days: %.4f (threshold %.4f, reports=%d).',
                    $windowDays,
                    $familyMismatchRate,
                    $maxFamilyMismatchRate,
                    $recentAiCount
                ),
                $kpiSeverity
            );

            $maxReviewingAgeHours = max(1, (int) config('services.inference.max_reviewing_age_hours', 24));
            $maxReviewingBacklog = max(1, (int) config('services.inference.max_reviewing_backlog', 200));
            $staleReviewingCount = (int) DiseaseReport::query()
                ->where('report_source', 'ai')
                ->where('status', 'reviewing')
                ->where('reported_at', '<', now()->subHours($maxReviewingAgeHours))
                ->count();

            $check(
                'inference.reviewing_backlog',
                $staleReviewingCount <= $maxReviewingBacklog,
                sprintf(
                    'AI reviewing backlog older than %d hours: %d (threshold %d).',
                    $maxReviewingAgeHours,
                    $staleReviewingCount,
                    $maxReviewingBacklog
                ),
                $kpiSeverity
            );
        }
    }

    $criticalFailures = collect($checks)->where('severity', 'critical')->where('ok', false)->count();

    if ($this->option('json')) {
        $this->line(json_encode([
            'status' => $criticalFailures === 0 ? 'ok' : 'failed',
            'critical_failures' => $criticalFailures,
            'checks' => $checks,
        ], JSON_PRETTY_PRINT));
    } else {
        foreach ($checks as $item) {
            $prefix = $item['ok'] ? '[OK]' : ($item['severity'] === 'critical' ? '[FAIL]' : '[WARN]');
            $this->line(sprintf('%s %s - %s', $prefix, $item['name'], $item['message']));
        }
    }

    return $criticalFailures === 0 ? 0 : 1;
})->purpose('Run production readiness checks for database, queue, storage, and inference service');

Artisan::command('ops:inference-kpi {--days= : Rolling window in days} {--json : Output JSON payload}', function () {
    if (! Schema::hasTable('disease_reports')) {
        $payload = [
            'status' => 'not_available',
            'message' => 'disease_reports table is unavailable.',
            'window_days' => 0,
            'summary' => [],
            'per_crop' => [],
        ];
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        } else {
            $this->warn($payload['message']);
        }

        return 0;
    }

    $windowDays = (int) ($this->option('days') ?? 0);
    if ($windowDays <= 0) {
        $windowDays = (int) config('services.inference.kpi_window_days', 7);
    }
    $windowDays = max(1, $windowDays);
    $since = now()->subDays($windowDays);

    $maxUncertainRate = (float) config('services.inference.max_uncertain_rate', 0.25);
    $maxFamilyMismatchRate = (float) config('services.inference.max_family_mismatch_rate', 0.10);
    $maxReviewingBacklog = max(1, (int) config('services.inference.max_reviewing_backlog', 200));
    $maxReviewingAgeHours = max(1, (int) config('services.inference.max_reviewing_age_hours', 24));

    $base = DiseaseReport::query()
        ->where('report_source', 'ai')
        ->where('reported_at', '>=', $since);

    $totalReports = (int) (clone $base)->count();
    $uncertainReports = (int) (clone $base)
        ->where(function ($query): void {
            $query
                ->where('description', 'like', '%marked uncertain%')
                ->orWhere('description', 'like', '%low confidence prediction%')
                ->orWhere('description', 'like', '%review-only mode%');
        })
        ->count();
    $mismatchReports = (int) (clone $base)
        ->where('description', 'like', '%does not match selected crop%')
        ->count();
    $staleReviewing = (int) DiseaseReport::query()
        ->where('report_source', 'ai')
        ->where('status', 'reviewing')
        ->where('reported_at', '<', now()->subHours($maxReviewingAgeHours))
        ->count();

    $uncertainRate = $totalReports > 0 ? round($uncertainReports / $totalReports, 4) : 0.0;
    $mismatchRate = $totalReports > 0 ? round($mismatchReports / $totalReports, 4) : 0.0;

    $perCropRows = DB::table('disease_reports as dr')
        ->leftJoin('crops as c', 'c.id', '=', 'dr.crop_id')
        ->where('dr.report_source', 'ai')
        ->where('dr.reported_at', '>=', $since)
        ->groupBy('dr.crop_id', 'c.name')
        ->orderByRaw('COUNT(*) DESC')
        ->selectRaw('dr.crop_id as crop_id')
        ->selectRaw("COALESCE(c.name, 'Unknown crop') as crop_name")
        ->selectRaw('COUNT(*) as total_reports')
        ->selectRaw(
            "SUM(CASE WHEN LOWER(COALESCE(dr.description, '')) LIKE '%marked uncertain%' " .
            "OR LOWER(COALESCE(dr.description, '')) LIKE '%low confidence prediction%' " .
            "OR LOWER(COALESCE(dr.description, '')) LIKE '%review-only mode%' " .
            "THEN 1 ELSE 0 END) as uncertain_reports"
        )
        ->selectRaw(
            "SUM(CASE WHEN LOWER(COALESCE(dr.description, '')) LIKE '%does not match selected crop%' " .
            "THEN 1 ELSE 0 END) as mismatch_reports"
        )
        ->get()
        ->map(function ($row) use ($maxUncertainRate, $maxFamilyMismatchRate) {
            $total = max(1, (int) $row->total_reports);
            $uncertainRateCrop = round(((int) $row->uncertain_reports) / $total, 4);
            $mismatchRateCrop = round(((int) $row->mismatch_reports) / $total, 4);

            return [
                'crop_id' => (int) $row->crop_id,
                'crop_name' => (string) $row->crop_name,
                'total_reports' => (int) $row->total_reports,
                'uncertain_reports' => (int) $row->uncertain_reports,
                'uncertain_rate' => $uncertainRateCrop,
                'uncertain_rate_ok' => $uncertainRateCrop <= $maxUncertainRate,
                'mismatch_reports' => (int) $row->mismatch_reports,
                'mismatch_rate' => $mismatchRateCrop,
                'mismatch_rate_ok' => $mismatchRateCrop <= $maxFamilyMismatchRate,
            ];
        })
        ->values()
        ->all();

    $payload = [
        'status' => 'ok',
        'window_days' => $windowDays,
        'since' => $since->toISOString(),
        'thresholds' => [
            'max_uncertain_rate' => $maxUncertainRate,
            'max_family_mismatch_rate' => $maxFamilyMismatchRate,
            'max_reviewing_backlog' => $maxReviewingBacklog,
            'max_reviewing_age_hours' => $maxReviewingAgeHours,
        ],
        'summary' => [
            'total_reports' => $totalReports,
            'uncertain_reports' => $uncertainReports,
            'uncertain_rate' => $uncertainRate,
            'uncertain_rate_ok' => $uncertainRate <= $maxUncertainRate,
            'mismatch_reports' => $mismatchReports,
            'mismatch_rate' => $mismatchRate,
            'mismatch_rate_ok' => $mismatchRate <= $maxFamilyMismatchRate,
            'reviewing_backlog_older_than_threshold' => $staleReviewing,
            'reviewing_backlog_ok' => $staleReviewing <= $maxReviewingBacklog,
        ],
        'per_crop' => $perCropRows,
    ];

    if ($this->option('json')) {
        $this->line(json_encode($payload, JSON_PRETTY_PRINT));
    } else {
        $this->line(sprintf(
            'AI KPI (%dd): total=%d uncertain=%.4f mismatch=%.4f stale_reviewing=%d',
            $windowDays,
            $totalReports,
            $uncertainRate,
            $mismatchRate,
            $staleReviewing
        ));
        foreach ($perCropRows as $row) {
            $this->line(sprintf(
                '- crop=%s total=%d uncertain=%.4f mismatch=%.4f',
                $row['crop_name'],
                $row['total_reports'],
                $row['uncertain_rate'],
                $row['mismatch_rate']
            ));
        }
    }

    return 0;
})->purpose('Show live AI inference KPIs globally and per crop over a rolling window');

Artisan::command('ops:release-gate {--target=autonomous : controlled|autonomous} {--json : Output JSON payload}', function (InferencePipelineService $inference) {
    $target = strtolower(trim((string) $this->option('target')));
    if (! in_array($target, ['controlled', 'autonomous'], true)) {
        $this->error("Invalid --target value '{$target}'. Expected 'controlled' or 'autonomous'.");
        return 1;
    }

    $targetThresholds = (array) config("services.inference.release_gate.{$target}", []);
    $windowDays = max(1, (int) config('services.inference.kpi_window_days', 7));
    $maxUncertainRate = (float) ($targetThresholds['max_uncertain_rate']
        ?? config('services.inference.max_uncertain_rate', 0.25));
    $maxFamilyMismatchRate = (float) ($targetThresholds['max_family_mismatch_rate']
        ?? config('services.inference.max_family_mismatch_rate', 0.10));
    $maxReviewingBacklog = max(1, (int) ($targetThresholds['max_reviewing_backlog']
        ?? config('services.inference.max_reviewing_backlog', 200)));
    $maxReviewingAgeHours = max(1, (int) ($targetThresholds['max_reviewing_age_hours']
        ?? config('services.inference.max_reviewing_age_hours', 24)));
    $minAiReportsWindow = max(1, (int) ($targetThresholds['min_ai_reports_window'] ?? 100));
    $minReportsPerCrop = max(1, (int) ($targetThresholds['min_reports_per_crop'] ?? 20));
    $since = now()->subDays($windowDays);

    Artisan::call('ops:health-check', ['--json' => true]);
    $healthPayload = json_decode((string) Artisan::output(), true);
    if (! is_array($healthPayload)) {
        $healthPayload = [
            'critical_failures' => 1,
            'checks' => [],
        ];
    }

    $checks = collect((array) ($healthPayload['checks'] ?? []));
    $findCheck = static fn (string $name) => $checks->firstWhere('name', $name) ?? ['ok' => false, 'message' => 'missing'];

    $inferenceEnabled = (bool) config('services.inference.enabled', false);
    $inferenceReport = $inference->healthReport();
    $reviewOnly = (bool) config('services.inference.review_only_mode', false);
    $criticalFailures = (int) ($healthPayload['critical_failures'] ?? 1);

    $kpiAvailable = Schema::hasTable('disease_reports');
    $totalReports = 0;
    $uncertainReports = 0;
    $mismatchReports = 0;
    $uncertainRate = 0.0;
    $mismatchRate = 0.0;
    $staleReviewing = 0;
    $perCropRows = [];

    if ($kpiAvailable) {
        $recentAiBase = DiseaseReport::query()
            ->where('report_source', 'ai')
            ->where('reported_at', '>=', $since);

        $totalReports = (int) (clone $recentAiBase)->count();
        $uncertainReports = (int) (clone $recentAiBase)
            ->where(function ($query): void {
                $query
                    ->where('description', 'like', '%marked uncertain%')
                    ->orWhere('description', 'like', '%low confidence prediction%')
                    ->orWhere('description', 'like', '%review-only mode%');
            })
            ->count();
        $mismatchReports = (int) (clone $recentAiBase)
            ->where('description', 'like', '%does not match selected crop%')
            ->count();
        $uncertainRate = $totalReports > 0 ? round($uncertainReports / $totalReports, 4) : 0.0;
        $mismatchRate = $totalReports > 0 ? round($mismatchReports / $totalReports, 4) : 0.0;
        $staleReviewing = (int) DiseaseReport::query()
            ->where('report_source', 'ai')
            ->where('status', 'reviewing')
            ->where('reported_at', '<', now()->subHours($maxReviewingAgeHours))
            ->count();

        $perCropRows = DB::table('disease_reports as dr')
            ->leftJoin('crops as c', 'c.id', '=', 'dr.crop_id')
            ->where('dr.report_source', 'ai')
            ->where('dr.reported_at', '>=', $since)
            ->groupBy('dr.crop_id', 'c.name')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw('dr.crop_id as crop_id')
            ->selectRaw("COALESCE(c.name, 'Unknown crop') as crop_name")
            ->selectRaw('COUNT(*) as total_reports')
            ->get()
            ->map(static fn ($row): array => [
                'crop_id' => (int) $row->crop_id,
                'crop_name' => (string) $row->crop_name,
                'total_reports' => (int) $row->total_reports,
            ])
            ->values()
            ->all();
    }

    $lowVolumeCrops = collect($perCropRows)
        ->filter(static fn (array $row): bool => (int) ($row['total_reports'] ?? 0) < $minReportsPerCrop)
        ->map(static fn (array $row): string => "{$row['crop_name']}=".((int) ($row['total_reports'] ?? 0)))
        ->values()
        ->all();

    $conditions = [
        [
            'name' => 'ops.critical_failures_zero',
            'required' => true,
            'ok' => $criticalFailures === 0,
            'message' => "Critical failures: {$criticalFailures}",
        ],
        [
            'name' => 'inference.enabled',
            'required' => true,
            'ok' => $inferenceEnabled,
            'message' => $inferenceEnabled
                ? 'Inference is enabled.'
                : 'Inference is disabled.',
        ],
        [
            'name' => 'inference.service',
            'required' => true,
            'ok' => (bool) ($findCheck('inference.service')['ok'] ?? false),
            'message' => (string) ($findCheck('inference.service')['message'] ?? 'inference.service check missing'),
        ],
        [
            'name' => 'inference.contract_ok',
            'required' => true,
            'ok' => (bool) ($inferenceReport['contract_ok'] ?? false),
            'message' => (bool) ($inferenceReport['contract_ok'] ?? false)
                ? 'Inference runtime contract matches expected configuration.'
                : implode('; ', (array) ($inferenceReport['contract_messages'] ?? ['contract mismatch'])),
        ],
    ];

    if ($target === 'controlled') {
        $conditions[] = [
            'name' => 'inference.review_only_mode_enabled',
            'required' => true,
            'ok' => $reviewOnly,
            'message' => $reviewOnly
                ? 'Review-only mode is enabled (required for controlled rollout).'
                : 'Review-only mode is disabled; controlled rollout requires review-only mode.',
        ];
    } else {
        $conditions[] = [
            'name' => 'inference.review_only_mode_disabled',
            'required' => true,
            'ok' => ! $reviewOnly,
            'message' => ! $reviewOnly
                ? 'Review-only mode is disabled.'
                : 'Review-only mode is enabled; autonomous release blocked.',
        ];
    }

    if (! $kpiAvailable) {
        $conditions[] = [
            'name' => 'inference.kpi_source_available',
            'required' => true,
            'ok' => false,
            'message' => 'disease_reports table unavailable for KPI gating.',
        ];
    } else {
        $conditions[] = [
            'name' => 'inference.kpi_window_volume',
            'required' => true,
            'ok' => $totalReports >= $minAiReportsWindow,
            'message' => sprintf(
                'AI reports in last %d days: %d (minimum %d).',
                $windowDays,
                $totalReports,
                $minAiReportsWindow
            ),
        ];
        $conditions[] = [
            'name' => 'inference.kpi_uncertain_rate_target',
            'required' => true,
            'ok' => $uncertainRate <= $maxUncertainRate,
            'message' => sprintf(
                'Uncertain rate: %.4f (threshold %.4f).',
                $uncertainRate,
                $maxUncertainRate
            ),
        ];
        $conditions[] = [
            'name' => 'inference.kpi_family_mismatch_rate_target',
            'required' => true,
            'ok' => $mismatchRate <= $maxFamilyMismatchRate,
            'message' => sprintf(
                'Crop-family mismatch rate: %.4f (threshold %.4f).',
                $mismatchRate,
                $maxFamilyMismatchRate
            ),
        ];
        $conditions[] = [
            'name' => 'inference.reviewing_backlog_target',
            'required' => true,
            'ok' => $staleReviewing <= $maxReviewingBacklog,
            'message' => sprintf(
                'Reviewing backlog older than %d hours: %d (threshold %d).',
                $maxReviewingAgeHours,
                $staleReviewing,
                $maxReviewingBacklog
            ),
        ];

        $lowVolumeMessage = $lowVolumeCrops === []
            ? sprintf('All crops in window meet minimum per-crop volume %d.', $minReportsPerCrop)
            : 'Below per-crop minimum: '.implode(', ', array_slice($lowVolumeCrops, 0, 8));
        if (count($lowVolumeCrops) > 8) {
            $lowVolumeMessage .= ', ...';
        }

        $conditions[] = [
            'name' => 'inference.kpi_per_crop_volume',
            'required' => $target === 'autonomous',
            'ok' => $lowVolumeCrops === [],
            'message' => $lowVolumeMessage,
        ];
    }

    $hardFailures = collect($conditions)
        ->where('required', true)
        ->where('ok', false)
        ->count();

    $gatePass = $hardFailures === 0;
    $nextAction = $gatePass
        ? 'GO'
        : ($target === 'autonomous'
            ? 'NO_GO_KEEP_CONTROLLED_ROLLOUT'
            : 'NO_GO_FIX_PIPELINE_BEFORE_CONTROLLED');

    $payload = [
        'status' => $gatePass ? 'go' : 'no_go',
        'target' => $target,
        'gate_pass' => $gatePass,
        'hard_failures' => $hardFailures,
        'thresholds' => [
            'window_days' => $windowDays,
            'max_uncertain_rate' => $maxUncertainRate,
            'max_family_mismatch_rate' => $maxFamilyMismatchRate,
            'max_reviewing_backlog' => $maxReviewingBacklog,
            'max_reviewing_age_hours' => $maxReviewingAgeHours,
            'min_ai_reports_window' => $minAiReportsWindow,
            'min_reports_per_crop' => $minReportsPerCrop,
        ],
        'kpi_snapshot' => [
            'available' => $kpiAvailable,
            'since' => $since->toISOString(),
            'total_reports' => $totalReports,
            'uncertain_reports' => $uncertainReports,
            'uncertain_rate' => $uncertainRate,
            'mismatch_reports' => $mismatchReports,
            'mismatch_rate' => $mismatchRate,
            'reviewing_backlog_older_than_threshold' => $staleReviewing,
            'below_min_per_crop' => $lowVolumeCrops,
        ],
        'conditions' => $conditions,
        'next_action' => $nextAction,
        'generated_at' => now()->toISOString(),
    ];

    if ($this->option('json')) {
        $this->line(json_encode($payload, JSON_PRETTY_PRINT));
    } else {
        $this->line(strtoupper($payload['status'])." target={$target} hard_failures={$hardFailures}");
        foreach ($conditions as $condition) {
            $prefix = $condition['ok'] ? '[OK]' : ($condition['required'] ? '[FAIL]' : '[WARN]');
            $this->line("{$prefix} {$condition['name']} - {$condition['message']}");
        }
    }

    return $gatePass ? 0 : 1;
})->purpose('Automatic go/no-go release gate for controlled or autonomous AI rollout');

Artisan::command('governance:check {--json : Output JSON payload}', function () {
    $requiredRoles = ['super_admin', 'admin', 'supporter', 'expert', 'farmer'];
    $allowedLevels = ['national', 'region', 'zone', 'woreda', 'kebele', '*'];
    $requiredActions = [
        'farm.view_any',
        'farm.view',
        'farm.create',
        'farm.update',
        'farm.delete',
        'disease_report.view_any',
        'disease_report.verify',
        'alert.view_any',
        'alert.update',
        'delegation.manage',
    ];

    $results = [];
    $add = function (string $name, bool $ok, string $message, string $severity = 'critical') use (&$results): void {
        $results[] = compact('name', 'ok', 'message', 'severity');
    };

    $roleNames = Role::query()->pluck('name')->map(fn ($v) => strtolower((string) $v))->all();
    foreach ($requiredRoles as $role) {
        $add("roles.{$role}", in_array($role, $roleNames, true), "Role '{$role}' must exist.");
    }

    $activeSuperAdmins = User::query()
        ->where('is_active', 1)
        ->whereHas('role', fn ($q) => $q->where('name', 'super_admin'))
        ->count();
    $add('users.active_super_admin_count', $activeSuperAdmins > 0, 'At least one active super_admin must exist.');

    $matrix = (array) config('authority_matrix.actions', []);
    foreach ($requiredActions as $action) {
        $add("authority_matrix.actions.{$action}", array_key_exists($action, $matrix), "Authority matrix action '{$action}' must be configured.");
    }

    foreach ($matrix as $action => $ruleSet) {
        foreach ((array) $ruleSet as $role => $levels) {
            foreach ((array) $levels as $level) {
                $normalized = strtolower(trim((string) $level));
                $add(
                    "authority_matrix.levels.{$action}.{$role}.{$normalized}",
                    in_array($normalized, $allowedLevels, true),
                    "Invalid level '{$normalized}' in action '{$action}' for role '{$role}'.",
                    'critical'
                );
            }
        }
    }

    $backofficeMissingLevel = User::query()
        ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'supporter', 'expert']))
        ->where(function ($q): void {
            $q->whereNull('admin_level')->orWhere('admin_level', '');
        })
        ->count();
    $add('users.backoffice_admin_level', $backofficeMissingLevel === 0, 'All admin/supporter/expert users should have admin_level assigned.', 'warning');

    $backofficeMissingRegion = User::query()
        ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'supporter', 'expert']))
        ->whereNull('region_id')
        ->count();
    $add('users.backoffice_region', $backofficeMissingRegion === 0, 'All admin/supporter/expert users should have primary region assigned.', 'warning');

    $add('soft_delete.users', Schema::hasColumn('users', 'deleted_at'), 'users table has no deleted_at; account lifecycle relies on is_active flag.', 'warning');
    $add('soft_delete.roles', Schema::hasColumn('roles', 'deleted_at'), 'roles table has no deleted_at; role lifecycle is currently hard-delete based.', 'warning');
    $add('soft_delete.regions', Schema::hasColumn('regions', 'deleted_at'), 'regions table has no deleted_at; hierarchy lifecycle is currently hard-delete based.', 'warning');

    $criticalFailures = collect($results)->where('severity', 'critical')->where('ok', false)->count();

    if ($this->option('json')) {
        $this->line(json_encode([
            'status' => $criticalFailures === 0 ? 'ok' : 'failed',
            'critical_failures' => $criticalFailures,
            'checks' => $results,
        ], JSON_PRETTY_PRINT));
    } else {
        foreach ($results as $item) {
            $prefix = $item['ok'] ? '[OK]' : ($item['severity'] === 'critical' ? '[FAIL]' : '[WARN]');
            $this->line(sprintf('%s %s - %s', $prefix, $item['name'], $item['message']));
        }
    }

    return $criticalFailures === 0 ? 0 : 1;
})->purpose('Validate role governance, authority matrix consistency, and lifecycle guardrails');

Artisan::command('ops:reconcile-migrations {--apply : Persist reconciliation records into migrations table}', function () {
    $pending = collect(DB::table('migrations')->pluck('migration')->all());
    $existing = collect(File::files(database_path('migrations')))
        ->map(fn ($file) => pathinfo($file->getFilename(), PATHINFO_FILENAME))
        ->values();

    $knownChecks = [
        '0001_01_01_000000_create_users_table' => fn (): bool => Schema::hasTable('users'),
        '2026_02_12_190000_create_my_farm_domain_tables' => fn (): bool =>
            Schema::hasTable('farms')
            && Schema::hasTable('plots')
            && Schema::hasTable('plantings')
            && Schema::hasTable('roles')
            && Schema::hasTable('regions')
            && Schema::hasTable('crops'),
        '2026_02_12_190100_align_users_table_for_my_farm' => fn (): bool =>
            Schema::hasColumn('users', 'role_id')
            && Schema::hasColumn('users', 'region_id')
            && Schema::hasColumn('users', 'phone')
            && Schema::hasColumn('users', 'is_active'),
        '2026_02_16_074902_create_disease_reports_and_alerts_tables' => fn (): bool =>
            Schema::hasTable('disease_reports')
            && Schema::hasTable('alerts'),
        '2026_02_20_090000_create_delegation_audit_logs_table' => fn (): bool =>
            Schema::hasTable('delegation_audit_logs'),
    ];

    $reconcilable = [];
    foreach ($knownChecks as $migration => $check) {
        if ($pending->contains($migration)) {
            continue;
        }

        if (! $existing->contains($migration)) {
            continue;
        }

        $alreadyRecorded = DB::table('migrations')->where('migration', $migration)->exists();
        if ($alreadyRecorded) {
            continue;
        }

        if ($check()) {
            $reconcilable[] = $migration;
        }
    }

    if ($reconcilable === []) {
        $this->info('No migration records require reconciliation.');
        return 0;
    }

    foreach ($reconcilable as $migration) {
        $this->line("[RECONCILE] {$migration}");
    }

    if (! $this->option('apply')) {
        $this->warn('Dry-run mode. Re-run with --apply to persist.');
        return 0;
    }

    $nextBatch = (int) DB::table('migrations')->max('batch') + 1;
    foreach ($reconcilable as $migration) {
        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => $nextBatch,
        ]);
    }

    $this->info('Migration reconciliation records inserted.');
    return 0;
})->purpose('Safely reconcile migration records for schemas that already exist');

Artisan::command('dev:bootstrap {--force : Create even if records exist}', function () {
    $force = (bool) $this->option('force');

    if (! $force && DB::table('farms')->exists()) {
        $this->info('Farms already exist; nothing to bootstrap.');
        return 0;
    }

    /** @var \App\Models\User|null $farmer */
    $farmer = \App\Models\User::query()
        ->where('is_active', true)
        ->whereHas('role', fn ($q) => $q->whereRaw('lower(name)=?', ['farmer']))
        ->first();

    if (! $farmer) {
        $this->error('No active farmer user found. Create a farmer user first.');
        return 1;
    }

    $regionId = (int) ($farmer->region_id ?? 0);
    if ($regionId <= 0) {
        $regionId = (int) (DB::table('regions')->orderBy('id')->value('id') ?? 0);
    }
    if ($regionId <= 0) {
        $this->error('No regions found. Run `php artisan db:seed` first.');
        return 1;
    }

    $cropId = (int) (DB::table('crops')->orderBy('id')->value('id') ?? 0);
    if ($cropId <= 0) {
        $this->error('No crops found. Run `php artisan db:seed` first.');
        return 1;
    }

    $farm = DB::transaction(function () use ($farmer, $regionId, $cropId) {
        $farm = \App\Models\Farm::create([
            'farmer_id' => $farmer->id,
            'region_id' => $regionId,
            'farm_name' => 'Demo Farm',
            'latitude' => 7.46,
            'longitude' => 38.30,
            'area_hectares' => 1.25,
            'farm_type' => 'crop',
            'is_active' => true,
        ]);

        $plot = \App\Models\Plot::create([
            'farm_id' => $farm->id,
            'plot_name' => 'Main Plot',
            'area_hectares' => 0.50,
            'soil_type' => 'loam',
            'is_active' => true,
        ]);

        \App\Models\Planting::create([
            'plot_id' => $plot->id,
            'crop_id' => $cropId,
            'planting_date' => now()->subDays(30)->toDateString(),
            'expected_harvest_date' => now()->addDays(60)->toDateString(),
            'status' => 'active',
            'is_active' => true,
        ]);

        return $farm;
    });

    $this->info(sprintf('Bootstrapped farm id=%d for farmer user id=%d', $farm->id, $farmer->id));
    return 0;
})->purpose('Bootstrap minimal dev data (farm/plot/planting) for mobile testing');
