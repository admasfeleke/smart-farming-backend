<x-filament-panels::page>
    @php
        $stats = $this->overviewStats();
        $cards = $this->triageCards();
    @endphp

    <style>
        .disease-review-hero {
            overflow: hidden;
            border-radius: 1.15rem;
            border: 1px solid #dbe7df;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        }
        .disease-review-hero-title {
            color: #102318;
            text-shadow: none;
        }
        .disease-review-hero-copy {
            max-width: 44rem;
            color: #475569;
            text-shadow: none;
        }
        .disease-review-hero-badge {
            display: inline-flex;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            background: #f0fdf4;
            padding: .35rem .75rem;
            color: #166534;
            font-size: .72rem;
            font-weight: 850;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .disease-review-kpi {
            position: relative;
            overflow: visible;
            display: flex;
            min-height: 150px;
            flex-direction: column;
            justify-content: space-between;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .05);
            padding: 1rem 1rem 1rem 1.35rem;
        }
        .disease-review-kpi::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: .35rem;
            background: #16a34a;
        }
        .disease-review-kpi-value {
            font-size: 1.85rem;
            line-height: 1;
            font-weight: 950;
            letter-spacing: -.055em;
            color: #132d1f;
            white-space: normal;
        }
        .disease-review-kpi-label {
            margin-top: .55rem;
            max-width: none;
            font-size: .82rem;
            font-weight: 760;
            line-height: 1.35;
            color: #334155;
            overflow-wrap: anywhere;
        }
        .disease-review-kpi-meta {
            margin-top: .95rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: .35rem;
            font-size: .72rem;
            font-weight: 820;
            line-height: 1.25;
            color: #475569;
        }
        @media (min-width: 1280px) {
            .disease-review-kpi-meta {
                grid-template-columns: minmax(0, 1fr) auto;
            }
        }
        .disease-review-kpi-meta span {
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .disease-review-kpi-bar {
            margin-top: .75rem;
            height: .42rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }
        .disease-review-kpi-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 760px) {
            .disease-review-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1180px) {
            .disease-review-kpi-grid {
                grid-template-columns: repeat(3, minmax(250px, 1fr));
            }
        }
        @media (min-width: 1680px) {
            .disease-review-kpi-grid {
                grid-template-columns: repeat(5, minmax(220px, 1fr));
            }
        }
        .disease-review-kpi-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #16a34a, #84cc16);
        }
        .disease-review-kpi-alert {
            background: #ffffff;
            border-color: #fed7aa;
        }
        .disease-review-kpi-alert::before {
            background: #f59e0b;
        }
        .disease-review-kpi-danger {
            background: #ffffff;
            border-color: #fecaca;
        }
        .disease-review-kpi-danger::before {
            background: #dc2626;
        }
        .disease-review-kpi-ai::before {
            background: #0284c7;
        }
        .disease-review-kpi-complete::before {
            background: #334155;
        }
        .disease-review-rule {
            border: 1px solid #dbe7df;
            border-left: 4px solid #16a34a;
            background: #f8fafc;
            box-shadow: none;
            color: #0f172a;
        }
        .disease-review-rule-title {
            color: #052e16;
            font-weight: 900;
        }
        .disease-review-rule-copy {
            color: #334155;
            line-height: 1.55;
        }
        .fi-ta-row:hover {
            background: rgba(240, 253, 244, .55) !important;
        }
        .triage-card-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
        .triage-card {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            border: 1px solid rgba(148, 163, 184, .24);
            background: #fff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .triage-card:hover {
            transform: translateY(-3px);
            border-color: rgba(34, 197, 94, .45);
            box-shadow: 0 14px 28px rgba(15, 23, 42, .10);
        }
        .triage-card-image {
            position: relative;
            height: 230px;
            background:
                radial-gradient(circle at top, rgba(34,197,94,.2), transparent 18rem),
                linear-gradient(135deg, #ecfdf5, #f8fafc);
        }
        .triage-card-image img {
            height: 100%;
            width: 100%;
            object-fit: cover;
        }
        .triage-card-image::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(15,23,42,0) 45%, rgba(15,23,42,.70) 100%);
            pointer-events: none;
        }
        .triage-card-danger {
            border-top: 5px solid #dc2626;
        }
        .triage-card-warning {
            border-top: 5px solid #d97706;
        }
        .triage-card-normal {
            border-top: 5px solid #16a34a;
        }
        .triage-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: .28rem .55rem;
            font-size: .72rem;
            font-weight: 750;
            line-height: 1;
        }
        .triage-chip-dark {
            background: rgba(15, 23, 42, .78);
            color: white;
            border: none;
            backdrop-filter: blur(8px);
            text-shadow: none;
        }
        .triage-chip-soft {
            background: #f1f5f9;
            color: #334155;
            border: none;
        }
        .triage-score-bar {
            height: .45rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }
        .triage-score-bar > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #16a34a, #65a30d);
        }
        .triage-card-title {
            color: #ffffff;
            text-shadow: 0 1px 8px rgba(0, 0, 0, .45);
        }
        .triage-card-subtitle {
            color: rgba(255, 255, 255, .86);
            text-shadow: 0 1px 6px rgba(0, 0, 0, .35);
        }
        .triage-board-title {
            color: #020617;
        }
        .triage-board-copy {
            color: #334155;
            font-weight: 560;
        }
        .triage-score-label {
            color: #334155;
            font-weight: 700;
        }
        .triage-score-value {
            color: #64748b;
            font-weight: 800;
        }
    </style>

    <div class="disease-review-hero p-5 md:p-6">
        <div class="grid gap-5 lg:grid-cols-[1.1fr_.9fr] lg:items-end">
            <div>
                <div class="disease-review-hero-badge">
                    Crop disease triage
                </div>
                <h2 class="disease-review-hero-title mt-3 text-2xl font-black tracking-tight md:text-3xl">
                    Review crop disease evidence, not just records
                </h2>
                <p class="disease-review-hero-copy mt-2 text-sm font-medium leading-6">
                    Each case combines farmer image evidence, offline provisional inference, server AI scores, crop context, and final Subject Matter Specialist decision.
                </p>
            </div>

            <div class="disease-review-rule rounded-xl p-4 text-sm text-slate-700">
                <div class="disease-review-rule-title">Review rule</div>
                <div class="disease-review-rule-copy mt-1">
                    Treat AI output as supporting evidence. Confirm pesticide or treatment guidance only after checking the image, crop, confidence, and field context.
                </div>
            </div>
        </div>
    </div>

    <div class="disease-review-kpi-grid">
        <div class="disease-review-kpi disease-review-kpi-alert">
            <div class="disease-review-kpi-value text-amber-700">{{ number_format($stats['reviewing']) }}</div>
            <div class="disease-review-kpi-label">Cases waiting for SMS action</div>
            <div class="disease-review-kpi-meta">
                <span>Queue load</span>
                <span>{{ number_format($stats['total']) }} total</span>
            </div>
        </div>

        <div class="disease-review-kpi disease-review-kpi-danger">
            <div class="disease-review-kpi-value text-red-700">{{ number_format($stats['highRisk']) }}</div>
            <div class="disease-review-kpi-label">High / critical disease risk</div>
            <div class="disease-review-kpi-meta">
                <span>Prioritize first</span>
                <span>{{ $stats['total'] > 0 ? round(($stats['highRisk'] / $stats['total']) * 100) : 0 }}%</span>
            </div>
        </div>

        <div class="disease-review-kpi">
            <div class="disease-review-kpi-value text-emerald-700">{{ $stats['imageCoverage'] }}</div>
            <div class="disease-review-kpi-label">Image evidence coverage</div>
            <div class="disease-review-kpi-meta">
                <span>{{ number_format($stats['withImages']) }} with images</span>
                <span>{{ number_format($stats['total']) }} total</span>
            </div>
            <div class="disease-review-kpi-bar">
                <span style="width: {{ $stats['imageCoverage'] }}"></span>
            </div>
        </div>

        <div class="disease-review-kpi disease-review-kpi-ai">
            <div class="disease-review-kpi-value text-sky-700">{{ $stats['aiCoverage'] }}</div>
            <div class="disease-review-kpi-label">Server AI evidence coverage</div>
            <div class="disease-review-kpi-meta">
                <span>{{ number_format($stats['aiEvidence']) }} analyzed</span>
                <span>Top scores/candidate</span>
            </div>
            <div class="disease-review-kpi-bar">
                <span style="width: {{ $stats['aiCoverage'] }}"></span>
            </div>
        </div>

        <div class="disease-review-kpi disease-review-kpi-complete">
            <div class="disease-review-kpi-value text-slate-800">{{ $stats['reviewCompletion'] }}</div>
            <div class="disease-review-kpi-label">Review completion</div>
            <div class="disease-review-kpi-meta">
                <span>{{ number_format($stats['reviewed']) }} decided</span>
                <span>Confirmed/rejected</span>
            </div>
            <div class="disease-review-kpi-bar">
                <span style="width: {{ $stats['reviewCompletion'] }}"></span>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 shadow-sm">
        <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
                <div class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Image-first triage board</div>
                <h3 class="triage-board-title mt-1 text-xl font-black tracking-tight">Priority cases needing SMS attention</h3>
                <p class="triage-board-copy mt-1 text-sm">Large evidence previews make leaf quality, disease pattern, and AI uncertainty visible before opening the record.</p>
            </div>
            <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm">
                {{ $cards->count() }} priority cards
            </div>
        </div>

        @if ($cards->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <div class="text-lg font-bold text-slate-900">No priority disease cases</div>
                <div class="mt-1 text-sm text-slate-600">New high-risk or reviewing reports will appear here automatically.</div>
            </div>
        @else
            <div class="triage-card-grid">
                @foreach ($cards as $case)
                    @php
                        $image = $case->backofficeOriginalImageSrc();
                        $scores = $case->backofficeInferenceTopScores();
                        $confidence = $case->backofficeFindingConfidence();
                        $tone = $this->cardTone($case);
                    @endphp
                    <article class="triage-card triage-card-{{ $tone }}">
                        <button
                            type="button"
                            class="block w-full text-left"
                            wire:click="mountTableAction('viewDetails', '{{ $case->getKey() }}')"
                        >
                            <div class="triage-card-image">
                                @if ($image)
                                    <img src="{{ $image }}" alt="Leaf evidence for report {{ $case->id }}">
                                @else
                                    <div class="flex h-full items-center justify-center text-center">
                                        <div>
                                            <div class="text-4xl font-black text-emerald-800/20">LEAF</div>
                                            <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">No image evidence</div>
                                        </div>
                                    </div>
                                @endif

                                <div class="absolute left-3 right-3 top-3 z-10 flex items-start justify-between gap-2">
                                    <span class="triage-chip triage-chip-dark">{{ ucfirst($case->severity ?: 'low') }} risk</span>
                                    <span class="triage-chip triage-chip-dark">{{ $confidence !== null ? round($confidence * 100, 0).'%' : 'No AI %' }}</span>
                                </div>

                            </div>
                        </button>

                        <div class="space-y-3 p-4">
                            <button
                                type="button"
                                class="block w-full text-left"
                                wire:click="mountTableAction('viewDetails', '{{ $case->getKey() }}')"
                            >
                                <div class="text-lg font-black leading-tight text-slate-950">
                                    {{ $case->backofficeFindingName() }}
                                </div>
                                <div class="mt-1 text-sm font-bold text-slate-700">
                                    {{ ucfirst($case->backofficeFindingStage()) }} | {{ $case->crop?->name ?? 'Unknown crop' }}
                                </div>
                            </button>

                            <div class="flex flex-wrap gap-2">
                                <span class="triage-chip triage-chip-soft">{{ ucfirst($case->status ?: 'new') }}</span>
                                <span class="triage-chip triage-chip-soft">{{ $case->plot?->farm?->farm_name ?? 'No farm' }}</span>
                                <span class="triage-chip triage-chip-soft">{{ $case->plot?->farm?->region?->name ?? 'No region' }}</span>
                            </div>

                            @if (! empty($scores))
                                <div class="space-y-2">
                                    @foreach (array_slice($scores, 0, 3) as $score)
                                        @php $percent = round($score['score'] * 100, 1); @endphp
                                        <div>
                                            <div class="mb-1 flex items-center justify-between gap-3 text-xs">
                                                <span class="triage-score-label truncate">{{ $score['label'] }}</span>
                                                <span class="triage-score-value">{{ $percent }}%</span>
                                            </div>
                                            <div class="triage-score-bar">
                                                <span style="width: {{ max(2, min(100, $percent)) }}%"></span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-xl bg-slate-100 p-3 text-sm text-slate-600">
                                    No top-score AI evidence stored yet. Review image and farmer context manually.
                                </div>
                            @endif

                            <div class="flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500">
                                <span>Report #{{ $case->id }}</span>
                                <span>{{ optional($case->reported_at)->diffForHumans() ?? 'No date' }}</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white/95 p-1 shadow-sm">
        <div class="px-4 pb-2 pt-3">
            <div class="text-xs font-bold uppercase tracking-[.18em] text-slate-500">Search and audit table</div>
        </div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
