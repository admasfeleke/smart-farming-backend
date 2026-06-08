<?php

namespace App\Services;

use App\Models\DiseaseReport;
use App\Models\TreatmentRecommendation;

class DiseaseTreatmentGuidanceService
{
    /**
     * @return array<string, mixed>
     */
    public function build(DiseaseReport $report): array
    {
        $status = strtolower(trim((string) $report->status));
        $description = strtolower((string) ($report->description ?? ''));
        $diseaseKey = $this->normalizeDiseaseKey((string) ($report->disease_name ?? ''));
        $confidence = is_numeric($report->confidence_score) ? (float) $report->confidence_score : null;
        $minConfidence = (float) config('services.inference.min_confidence', 0.75);
        $isRejected = $status === 'rejected';
        $isConfirmed = in_array($status, ['confirmed', 'verified'], true);
        $isMismatch = str_contains($description, 'does not match selected crop');
        $isUncertain = str_contains($description, 'marked uncertain')
            || (! $isConfirmed && str_contains($description, 'low confidence prediction'))
            || (! $isConfirmed && str_contains($description, 'review-only mode'))
            || (! $isConfirmed && $confidence !== null && $confidence < $minConfidence);

        $reliability = $this->reliabilityLevel($isConfirmed, $isUncertain, $isMismatch, $confidence, $minConfidence);
        $risk = $this->riskLevel((string) $report->severity, $isUncertain, $isMismatch);
        $family = $this->familyFromDisease($diseaseKey);

        if ($isRejected) {
            return $this->assemble(
                mode: 'do_not_treat',
                treatmentReady: false,
                reliability: $reliability,
                risk: $risk,
                family: $family,
                confidence: $confidence,
                notes: ['Diagnosis was rejected by reviewer.'],
                template: (array) config('treatment_guidance.rejected', [])
            );
        }

        if (! $isConfirmed || $isUncertain || $isMismatch) {
            $notes = [];
            if (! $isConfirmed) {
                $notes[] = 'Diagnosis is not yet confirmed by supporter/expert.';
            }
            if ($isUncertain) {
                $notes[] = 'Prediction confidence is below reliable treatment threshold.';
            }
            if ($isMismatch) {
                $notes[] = 'Predicted disease family does not match selected crop.';
            }

            return $this->assemble(
                mode: 'pending_review',
                treatmentReady: false,
                reliability: $reliability,
                risk: $risk,
                family: $family,
                confidence: $confidence,
                notes: $notes,
                template: (array) config('treatment_guidance.pending_review', [])
            );
        }

        if ($diseaseKey === '' || str_ends_with($diseaseKey, '_healthy') || $diseaseKey === 'healthy') {
            return $this->assemble(
                mode: 'monitor_only',
                treatmentReady: false,
                reliability: $reliability,
                risk: 'low',
                family: $family,
                confidence: $confidence,
                notes: ['No actionable disease treatment required for healthy result.'],
                template: (array) config('treatment_guidance.healthy', [])
            );
        }

        $databaseRecommendations = $this->approvedRecommendations($report, $diseaseKey);
        $template = $databaseRecommendations === []
            ? $this->resolveTemplate($diseaseKey, $family)
            : $this->templateFromDatabaseRecommendations($databaseRecommendations, $diseaseKey, $family);

        return $this->assemble(
            mode: 'treat',
            treatmentReady: true,
            reliability: $reliability,
            risk: $risk,
            family: $family,
            confidence: $confidence,
            notes: ['Treatment guidance is based on verified diagnosis and crop context.'],
            template: $template,
            databaseRecommendations: $databaseRecommendations,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTemplate(string $diseaseKey, ?string $family): array
    {
        $default = (array) config('treatment_guidance.default', []);
        $familyTemplate = $family !== null
            ? (array) data_get(config('treatment_guidance.families', []), $family, [])
            : [];
        $keywordTemplate = $this->keywordTemplate($diseaseKey);

        return array_replace_recursive($default, $familyTemplate, $keywordTemplate);
    }

    /**
     * @return array<int, TreatmentRecommendation>
     */
    private function approvedRecommendations(DiseaseReport $report, string $diseaseKey): array
    {
        $cropId = $report->crop_id !== null ? (int) $report->crop_id : null;
        $diseaseWords = array_values(array_filter(explode('_', $diseaseKey)));

        $records = TreatmentRecommendation::query()
            ->with(['crop', 'pesticideProduct'])
            ->approved()
            ->where(function ($query) use ($cropId): void {
                $query->whereNull('crop_id');
                if ($cropId !== null) {
                    $query->orWhere('crop_id', $cropId);
                }
            })
            ->where(function ($query) use ($diseaseKey, $diseaseWords): void {
                $query->whereNull('disease_key')->whereNull('disease_keyword');

                if ($diseaseKey !== '') {
                    $query->orWhere('disease_key', $diseaseKey);
                }

                foreach ($diseaseWords as $word) {
                    if (strlen($word) >= 4) {
                        $query->orWhere('disease_keyword', $word);
                    }
                }
            })
            ->get();

        return $records
            ->map(function (TreatmentRecommendation $record) use ($cropId, $diseaseKey, $diseaseWords): array {
                $score = 0;

                if ($record->crop_id !== null && $cropId !== null && (int) $record->crop_id === $cropId) {
                    $score += 100;
                }

                $recordDiseaseKey = $this->normalizeDiseaseKey((string) $record->disease_key);
                if ($recordDiseaseKey !== '' && $recordDiseaseKey === $diseaseKey) {
                    $score += 60;
                }

                $keyword = $this->normalizeDiseaseKey((string) $record->disease_keyword);
                if ($keyword !== '' && in_array($keyword, $diseaseWords, true)) {
                    $score += 30;
                }

                if ($record->recommendation_type === 'chemical') {
                    $score += 5;
                }

                return ['record' => $record, 'score' => $score];
            })
            ->filter(fn (array $item): bool => $item['score'] > 0 || $item['record']->crop_id === null)
            ->sortByDesc('score')
            ->pluck('record')
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * @param array<int, TreatmentRecommendation> $recommendations
     * @return array<string, mixed>
     */
    private function templateFromDatabaseRecommendations(array $recommendations, string $diseaseKey, ?string $family): array
    {
        $first = $recommendations[0] ?? null;
        if (! $first instanceof TreatmentRecommendation) {
            return $this->resolveTemplate($diseaseKey, $family);
        }

        $product = $first->pesticideProduct;

        return [
            'review_status' => 'approved',
            'expert_verified' => true,
            'registry_localized_content' => $first->localized_content,
            'registry_product_localized_names' => $product?->localized_names,
            'registry_product_localized_active_ingredients' => $product?->localized_active_ingredients,
            'verification_note' => 'Treatment guidance comes from approved backoffice treatment registry entries. Always confirm the physical product label before spraying.',
            'headline' => $first->title,
            'next_step' => $first->summary ?: 'Apply the approved treatment plan after checking crop stage, weather, and product label instructions.',
            'active_ingredient' => $product?->active_ingredient,
            'dosage' => $first->dosage_text,
            'ppe' => $first->ppe,
            'pre_harvest_interval' => $first->pre_harvest_interval_days !== null
                ? $first->pre_harvest_interval_days.' days'
                : null,
            're_entry_interval' => $first->re_entry_interval_hours !== null
                ? $first->re_entry_interval_hours.' hours'
                : null,
            'actions' => array_values(array_filter([
                $first->natural_treatment,
                $first->modern_treatment,
                $first->application_timing,
            ])),
            'monitoring' => array_values(array_map('strval', (array) $first->monitoring_steps)),
            'prevention' => array_values(array_map('strval', (array) $first->prevention_steps)),
            'escalate_if' => [
                'Symptoms continue spreading after the recommended observation period.',
                'More than one plot shows similar symptoms.',
                'Farmer cannot confirm safe product label, PPE, PHI, or REI.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function keywordTemplate(string $diseaseKey): array
    {
        $keywords = (array) config('treatment_guidance.keywords', []);
        foreach ($keywords as $keyword => $template) {
            if (str_contains($diseaseKey, (string) $keyword)) {
                return (array) $template;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $template
     * @param array<int, string> $notes
     * @return array<string, mixed>
     */
    private function assemble(
        string $mode,
        bool $treatmentReady,
        string $reliability,
        string $risk,
        ?string $family,
        ?float $confidence,
        array $notes,
        array $template,
        array $databaseRecommendations = [],
    ): array {
        $reviewStatus = strtolower(trim((string) ($template['review_status'] ?? 'draft')));
        $expertVerified = (bool) ($template['expert_verified'] ?? false);
        $verificationNote = $this->optionalTemplateString($template, 'verification_note');
        $hasApprovedDatabaseRecommendations = $databaseRecommendations !== [];
        $effectiveTreatmentReady = $treatmentReady && ($expertVerified || $hasApprovedDatabaseRecommendations);
        $advisoryTreatmentAvailable = $mode === 'treat';
        $finalNotes = $notes;
        if ($verificationNote !== null && $verificationNote !== '') {
            $finalNotes[] = $verificationNote;
        }
        if ($advisoryTreatmentAvailable && ! $effectiveTreatmentReady) {
            $finalNotes[] = 'Diagnosis is confirmed, but dosage, PHI, and REI must still be checked against a locally registered product label before spraying.';
        }

        return [
            'mode' => $mode,
            'treatment_ready' => $effectiveTreatmentReady,
            'advisory_treatment_available' => $advisoryTreatmentAvailable,
            'review_status' => $reviewStatus,
            'expert_verified' => $expertVerified,
            'source' => $hasApprovedDatabaseRecommendations ? 'database_registry' : 'fallback_config',
            'verification_note' => $verificationNote,
            'reliability' => $reliability,
            'risk_level' => $risk,
            'confidence_score' => $confidence !== null ? round($confidence, 4) : null,
            'crop_family' => $family,
            'headline' => (string) ($template['headline'] ?? ''),
            'next_step' => (string) ($template['next_step'] ?? ''),
            'active_ingredient' => $advisoryTreatmentAvailable
                ? $this->optionalTemplateString($template, 'active_ingredient')
                : null,
            'dosage' => $advisoryTreatmentAvailable
                ? $this->optionalTemplateString($template, 'dosage')
                : null,
            'ppe' => $advisoryTreatmentAvailable ? $this->optionalTemplateString($template, 'ppe') : null,
            'pre_harvest_interval' => $advisoryTreatmentAvailable
                ? $this->optionalTemplateString($template, 'pre_harvest_interval')
                : null,
            're_entry_interval' => $advisoryTreatmentAvailable
                ? $this->optionalTemplateString($template, 're_entry_interval')
                : null,
            'actions' => array_values(array_map('strval', (array) ($template['actions'] ?? []))),
            'monitoring' => array_values(array_map('strval', (array) ($template['monitoring'] ?? []))),
            'prevention' => array_values(array_map('strval', (array) ($template['prevention'] ?? []))),
            'escalate_if' => array_values(array_map('strval', (array) ($template['escalate_if'] ?? []))),
            'treatment_options' => $this->treatmentOptions($databaseRecommendations),
            'notes' => $finalNotes,
        ];
    }

    /**
     * @param array<int, TreatmentRecommendation> $recommendations
     * @return array<int, array<string, mixed>>
     */
    private function treatmentOptions(array $recommendations): array
    {
        return array_values(array_map(function (TreatmentRecommendation $recommendation): array {
            $product = $recommendation->pesticideProduct;

            return [
                'id' => $recommendation->id,
                'type' => $recommendation->recommendation_type,
                'title' => $recommendation->title,
                'localized_content' => $recommendation->localized_content,
                'crop' => $recommendation->crop?->name,
                'disease_key' => $recommendation->disease_key,
                'disease_keyword' => $recommendation->disease_keyword,
                'summary' => $recommendation->summary,
                'natural_treatment' => $recommendation->natural_treatment,
                'modern_treatment' => $recommendation->modern_treatment,
                'product_name' => $product?->product_name,
                'localized_product_names' => $product?->localized_names,
                'active_ingredient' => $product?->active_ingredient,
                'localized_active_ingredients' => $product?->localized_active_ingredients,
                'formulation' => $product?->formulation,
                'registration_status' => $product?->registration_status,
                'dosage' => $recommendation->dosage_text,
                'application_timing' => $recommendation->application_timing,
                'pre_harvest_interval_days' => $recommendation->pre_harvest_interval_days,
                're_entry_interval_hours' => $recommendation->re_entry_interval_hours,
                'max_applications' => $recommendation->max_applications,
                'ppe' => $recommendation->ppe,
                'restrictions' => $recommendation->restrictions,
                'monitoring_steps' => array_values(array_map('strval', (array) $recommendation->monitoring_steps)),
                'prevention_steps' => array_values(array_map('strval', (array) $recommendation->prevention_steps)),
            ];
        }, $recommendations));
    }

    /**
     * @param array<string, mixed> $template
     */
    private function optionalTemplateString(array $template, string $key): ?string
    {
        if (! array_key_exists($key, $template)) {
            return null;
        }

        $value = $template[$key];
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return $text;
    }

    private function normalizeDiseaseKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', '_', $normalized);

        return trim((string) preg_replace('/_+/', '_', $normalized), '_');
    }

    private function familyFromDisease(string $diseaseKey): ?string
    {
        if ($diseaseKey === '') {
            return null;
        }

        $parts = explode('_', $diseaseKey);
        $family = $parts[0] ?? null;
        if ($family === null || $family === '') {
            return null;
        }

        return $family === 'maize' ? 'corn' : $family;
    }

    private function reliabilityLevel(
        bool $isConfirmed,
        bool $isUncertain,
        bool $isMismatch,
        ?float $confidence,
        float $minConfidence
    ): string {
        if (! $isConfirmed || $isMismatch || $isUncertain) {
            return 'low';
        }

        if ($confidence === null) {
            return 'medium';
        }

        if ($confidence >= max(0.90, $minConfidence + 0.10)) {
            return 'high';
        }

        return 'medium';
    }

    private function riskLevel(string $severity, bool $isUncertain, bool $isMismatch): string
    {
        if ($isUncertain || $isMismatch) {
            return 'unknown';
        }

        $s = strtolower(trim($severity));
        if (in_array($s, ['critical', 'high', 'medium', 'low'], true)) {
            return $s;
        }

        return 'unknown';
    }
}
