<?php

namespace App\Support;

class DiseaseNaming
{
    public static function normalizeKey(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = (string) preg_replace('/[\s\-\/()]+/', '_', $normalized);
        $normalized = (string) preg_replace('/[^a-z0-9_]+/', '_', $normalized);
        $normalized = trim((string) preg_replace('/_+/', '_', $normalized), '_');

        $pendingAliases = [
            '',
            'pending_analysis',
            'analysis_pending',
            'pending',
            'processing',
            'queued',
            'submitted',
            'manual_review_required',
            'unknown',
            'unknown_issue',
            'healthy_or_unknown',
        ];

        if (in_array($normalized, $pendingAliases, true)) {
            return 'pending_analysis';
        }

        if ($normalized === 'maize_healthy' || $normalized === 'corn_healthy') {
            return 'corn_healthy';
        }

        if (str_starts_with($normalized, 'maize_')) {
            $normalized = 'corn_'.substr($normalized, strlen('maize_'));
        }

        if (str_starts_with($normalized, 'corn_maize_')) {
            $normalized = 'corn_'.substr($normalized, strlen('corn_maize_'));
        }

        return $normalized;
    }

    public static function displayLabel(?string $value): string
    {
        $normalized = self::normalizeKey($value);
        if ($normalized === 'pending_analysis') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\b([a-z])/',
            static fn (array $matches): string => strtoupper($matches[1]),
            str_replace('_', ' ', $normalized)
        );
    }

    public static function cropFamilyFromDiseaseKey(?string $value): ?string
    {
        $normalized = self::normalizeKey($value);
        if ($normalized === 'pending_analysis') {
            return null;
        }

        $family = strtok($normalized, '_');
        if ($family === false || $family === '') {
            return null;
        }

        return $family === 'maize' ? 'corn' : $family;
    }
}