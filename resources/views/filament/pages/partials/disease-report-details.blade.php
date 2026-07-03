@php
    $activeAssignments = $record->assignments
        ->where('status', 'active')
        ->values();
    $metadata = is_array($record->scan_metadata) ? $record->scan_metadata : [];
    $auditRows = array_values(array_filter(
        $record->backofficeInferenceAuditRows(),
        fn ($row) => filled($row['value'] ?? null)
    ));
    $topScores = $record->backofficeInferenceTopScores();
    $confidence = $record->backofficeFindingConfidence();
    $primaryImage = $items[0]['url'] ?? null;
    $severity = strtolower((string) ($record->severity ?: 'low'));
    $riskClass = in_array($severity, ['critical', 'high'], true)
        ? 'review-risk-danger'
        : ($severity === 'medium' ? 'review-risk-warning' : 'review-risk-normal');
@endphp

<style>
    .report-review {
        --review-ink: #0f172a;
        --review-muted: #64748b;
        --review-border: rgba(148, 163, 184, .26);
        --review-panel: #ffffff;
        --review-soft: #f8fafc;
    }
    .report-review-shell {
        display: grid;
        gap: 1rem;
    }
    @media (min-width: 980px) {
        .report-review-shell {
            grid-template-columns: minmax(360px, .95fr) minmax(420px, 1.05fr);
            align-items: start;
        }
    }
    .review-panel {
        border: 1px solid var(--review-border);
        border-radius: 1rem;
        background: var(--review-panel);
        box-shadow: 0 8px 18px rgba(15, 23, 42, .05);
        overflow: hidden;
    }
    .review-image-stage {
        position: relative;
        min-height: 430px;
        background:
            radial-gradient(circle at 25% 0%, rgba(34, 197, 94, .16), transparent 20rem),
            linear-gradient(135deg, #ecfdf5, #f8fafc);
    }
    .review-image-stage img {
        height: 100%;
        max-height: 560px;
        min-height: 430px;
        width: 100%;
        object-fit: contain;
        background: #020617;
    }
    .review-image-empty {
        display: flex;
        min-height: 430px;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #64748b;
    }
    .review-image-overlay {
        position: absolute;
        inset: auto 1rem 1rem 1rem;
        border-radius: 1rem;
        background: rgba(15, 23, 42, .78);
        padding: .9rem;
        color: white;
        backdrop-filter: blur(10px);
        box-shadow: 0 12px 26px rgba(0, 0, 0, .22);
    }
    .review-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }
    .review-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .32rem .62rem;
        font-size: .72rem;
        font-weight: 800;
        line-height: 1;
    }
    .review-chip-dark {
        background: rgba(255, 255, 255, .14);
        color: #fff;
        border: none;
        text-shadow: none;
    }
    .review-chip-soft {
        background: #f1f5f9;
        color: #334155;
        border: none;
    }
    .review-risk-danger {
        color: #b91c1c;
    }
    .review-risk-warning {
        color: #b45309;
    }
    .review-risk-normal {
        color: #15803d;
    }
    .review-heading {
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: -.03em;
        color: var(--review-ink);
    }
    .review-subtitle {
        margin-top: .25rem;
        color: var(--review-muted);
        font-size: .86rem;
        font-weight: 500;
    }
    .review-section {
        border: 1px solid var(--review-border);
        border-radius: 1rem;
        background: var(--review-soft);
        padding: .9rem;
    }
    .review-section-title {
        margin-bottom: .65rem;
        font-size: .72rem;
        font-weight: 900;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: #475569;
    }
    .review-kv-grid {
        display: grid;
        gap: .65rem;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    .review-kv {
        border-radius: .85rem;
        background: #fff;
        padding: .75rem;
    }
    .review-kv-label {
        font-size: .68rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .review-kv-value {
        margin-top: .3rem;
        color: #0f172a;
        font-size: .9rem;
        font-weight: 750;
        word-break: break-word;
    }
    .review-score {
        border-radius: .9rem;
        background: #fff;
        border: none;
        padding: .75rem;
    }
    .review-score-bar {
        height: .48rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e2e8f0;
    }
    .review-score-bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #16a34a, #65a30d);
    }
    .review-warning {
        border: 1px solid rgba(245, 158, 11, .3);
        border-radius: 1rem;
        background: #fffbeb;
        color: #78350f;
        padding: .8rem;
        font-size: .82rem;
        line-height: 1.45;
    }
    .review-metadata {
        max-height: 240px;
        overflow: auto;
    }
</style>

<div class="report-review space-y-4">
    <div class="report-review-shell">
        <div class="review-panel">
            <div class="review-image-stage">
                @if ($primaryImage)
                    <a href="{{ $primaryImage }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ $primaryImage }}" alt="Disease evidence for report {{ $record->id }}">
                    </a>
                @else
                    <div class="review-image-empty">
                        <div>
                            <div class="text-5xl font-black text-emerald-900/10">NO IMAGE</div>
                            <div class="mt-2 text-sm font-semibold">Farmer image evidence is not available.</div>
                        </div>
                    </div>
                @endif

                <div class="review-image-overlay">
                    <div class="review-chip-row">
                        <span class="review-chip review-chip-dark">{{ ucfirst($record->status ?: 'new') }}</span>
                        <span class="review-chip review-chip-dark">{{ ucfirst($record->backofficeFindingStage()) }}</span>
                        <span class="review-chip review-chip-dark">{{ $confidence !== null ? round($confidence * 100, 1).'%' : 'No confidence' }}</span>
                    </div>
                    <div class="mt-3 text-lg font-black leading-tight">{{ $record->backofficeFindingName() }}</div>
                    <div class="mt-1 text-xs font-semibold text-white/75">
                        Report #{{ $record->id }} | {{ $record->crop?->name ?? 'Unknown crop' }} | {{ optional($record->reported_at)->format('M d, Y H:i') ?? 'No date' }}
                    </div>
                </div>
            </div>

            <div class="p-4">
                <div class="review-chip-row">
                    <span class="review-chip review-chip-soft {{ $riskClass }}">{{ ucfirst($record->severity ?: 'low') }} risk</span>
                    <span class="review-chip review-chip-soft">{{ $record->report_source ?: 'unknown source' }}</span>
                    <span class="review-chip review-chip-soft">{{ count($items) }} evidence file{{ count($items) === 1 ? '' : 's' }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="review-panel p-4">
                <div class="review-heading">{{ $record->backofficeFindingName() }}</div>
                <div class="review-subtitle">
                    {{ ucfirst($record->backofficeFindingStage()) }} diagnosis evidence for expert verification.
                </div>

                <div class="mt-4 review-kv-grid">
                    <div class="review-kv">
                        <div class="review-kv-label">Risk</div>
                        <div class="review-kv-value {{ $riskClass }}">{{ ucfirst($record->severity ?: 'low') }}</div>
                    </div>
                    <div class="review-kv">
                        <div class="review-kv-label">Confidence</div>
                        <div class="review-kv-value">{{ $confidence !== null ? round($confidence * 100, 1).'%' : '-' }}</div>
                    </div>
                    <div class="review-kv">
                        <div class="review-kv-label">Status</div>
                        <div class="review-kv-value">{{ ucfirst($record->status ?: 'new') }}</div>
                    </div>
                    <div class="review-kv">
                        <div class="review-kv-label">Stored field</div>
                        <div class="review-kv-value">{{ $record->disease_name ?: '-' }}</div>
                    </div>
                </div>

                @if (! empty($record->description))
                    <div class="mt-4 review-section">
                        <div class="review-section-title">Case note</div>
                        <div class="text-sm leading-6 text-slate-700">{{ $record->description }}</div>
                    </div>
                @endif
            </div>

            <div class="review-panel p-4">
                <div class="review-section-title">Top model scores</div>
                @if (empty($topScores))
                    <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">No top-score AI evidence was stored for this report.</div>
                @else
                    <div class="space-y-2">
                        @foreach ($topScores as $score)
                            @php $percent = round($score['score'] * 100, 1); @endphp
                            <div class="review-score">
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <div class="truncate text-sm font-bold text-slate-800">{{ $score['label'] }}</div>
                                    <div class="text-xs font-black text-slate-500">{{ $percent }}%</div>
                                </div>
                                <div class="review-score-bar">
                                    <span style="width: {{ max(2, min(100, $percent)) }}%"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="review-section">
            <div class="review-section-title">Farm context</div>
            <div class="review-kv-grid">
                <div class="review-kv">
                    <div class="review-kv-label">Farmer</div>
                    <div class="review-kv-value">{{ $record->reporter?->name ?? '-' }}</div>
                </div>
                <div class="review-kv">
                    <div class="review-kv-label">Farm</div>
                    <div class="review-kv-value">{{ $record->plot?->farm?->farm_name ?? '-' }}</div>
                </div>
                <div class="review-kv">
                    <div class="review-kv-label">Plot</div>
                    <div class="review-kv-value">{{ $record->plot?->plot_name ?? '-' }}</div>
                </div>
                <div class="review-kv">
                    <div class="review-kv-label">Administrative Unit</div>
                    <div class="review-kv-value">{{ $record->plot?->farm?->region?->name ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="review-section">
            <div class="review-section-title">Assignment</div>
            @if ($activeAssignments->isEmpty())
                <div class="rounded-xl bg-white p-3 text-sm text-slate-600">No active assignment.</div>
            @else
                <div class="space-y-2">
                    @foreach ($activeAssignments as $assignment)
                        <div class="rounded-xl bg-white p-3 text-sm text-slate-700">
                            <div><strong>Assigned to:</strong> {{ $assignment->assignedTo?->name ?? '-' }}</div>
                            <div><strong>Priority:</strong> {{ $assignment->priority ?? '-' }}</div>
                            <div><strong>Assigned by:</strong> {{ $assignment->assignedBy?->name ?? '-' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="review-section">
            <div class="review-section-title">Review decision</div>
            <div class="space-y-2 text-sm text-slate-700">
                <div><strong>Reviewed by:</strong> {{ $record->reviewer?->name ?? '-' }}</div>
                <div><strong>Reviewed at:</strong> {{ optional($record->reviewed_at)->format('Y-m-d H:i') ?? '-' }}</div>
                <div><strong>Reason:</strong> {{ $record->decision_reason_code ?? '-' }}</div>
            </div>
            @if (! empty($record->decision_comment))
                <div class="mt-3 rounded-xl bg-white p-3 text-sm text-slate-700">{{ $record->decision_comment }}</div>
            @endif
        </div>
    </div>

    <div class="review-panel p-4">
        <div class="review-section-title">AI inference audit</div>
        @if (empty($auditRows))
            <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">No AI inference audit data is available yet.</div>
        @else
            <div class="review-kv-grid">
                @foreach ($auditRows as $row)
                    <div class="review-kv">
                        <div class="review-kv-label">{{ $row['label'] }}</div>
                        <div class="review-kv-value">{{ $row['value'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-4 review-warning">
            AI output is supporting evidence, not final authorization. Confirm treatment only after comparing the image, crop context, confidence, field symptoms, and local agronomic conditions.
        </div>
    </div>

    <details class="review-panel p-4">
        <summary class="cursor-pointer text-sm font-black text-slate-800">Technical scan metadata</summary>
        @if (empty($metadata))
            <div class="mt-3 text-sm text-slate-600">No scan metadata was submitted.</div>
        @else
            <div class="review-metadata mt-3 grid gap-2 md:grid-cols-2">
                @foreach ($metadata as $key => $value)
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <span class="font-bold text-slate-700">{{ str_replace('_', ' ', (string) $key) }}:</span>
                        <span class="text-slate-700">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </details>
</div>
