<?php

namespace App\Services;

use App\Models\CaseAssignment;
use App\Models\DiseaseReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DiseaseReportReviewService
{
    public function triage(
        DiseaseReport $report,
        User $reviewer,
        string $decision,
        ?float $confidenceScore,
        string $reasonCode,
        ?string $comment,
    ): DiseaseReport {
        $decision = strtolower(trim($decision));
        if (! in_array($decision, ['confirmed', 'rejected'], true)) {
            throw new InvalidArgumentException('Triage decision must be confirmed or rejected.');
        }

        $from = (string) $report->status;
        $comment = trim((string) $comment);
        $decisionReason = $decision === 'confirmed'
            ? ($reasonCode !== '' ? $reasonCode : 'supporter_triage')
            : ($reasonCode !== '' ? $reasonCode : 'supporter_reject_flag');

        return DB::transaction(function () use (
            $report,
            $reviewer,
            $decision,
            $confidenceScore,
            $decisionReason,
            $comment,
            $from
        ): DiseaseReport {
            $payload = [
                'status' => 'processing',
            ];

            if ($confidenceScore !== null) {
                $payload['confidence_score'] = max(0.0, min(1.0, $confidenceScore));
            }
            if (Schema::hasColumn('disease_reports', 'reviewed_by')) {
                $payload['reviewed_by'] = $reviewer->id;
            }
            if (Schema::hasColumn('disease_reports', 'reviewed_at')) {
                $payload['reviewed_at'] = now();
            }
            if (Schema::hasColumn('disease_reports', 'decision_reason_code')) {
                $payload['decision_reason_code'] = $decisionReason;
            }
            if (Schema::hasColumn('disease_reports', 'decision_comment')) {
                $payload['decision_comment'] = $comment !== '' ? $comment : 'Supporter triage recorded.';
            }
            if (Schema::hasColumn('disease_reports', 'scan_metadata')) {
                $payload['scan_metadata'] = is_array($report->scan_metadata) ? $report->scan_metadata : [];
                $payload['scan_metadata']['workflow_triage_status'] = $decision;
                $payload['scan_metadata']['workflow_triaged_by'] = $reviewer->id;
                $payload['scan_metadata']['workflow_triaged_at'] = now()->toISOString();
            }

            $report->forceFill($payload)->save();

            app(CaseAssignmentService::class)->escalateDiseaseReportToExpert(
                $report,
                $reviewer,
                $comment !== '' ? $comment : 'Escalated to expert after supporter triage.'
            );

            CaseAuditLogger::log(
                'disease_report',
                $report->id,
                'triage',
                $from,
                'processing',
                $comment !== '' ? $comment : 'Supporter triage recorded.',
                [
                    'decision_reason_code' => $decisionReason,
                    'reviewer_user_id' => $reviewer->id,
                    'supporter_decision' => $decision,
                ],
            );

            return $report->refresh();
        });
    }

    public function confirm(
        DiseaseReport $report,
        User $reviewer,
        string $diseaseName,
        string $severity,
        ?float $confidenceScore,
        string $reasonCode,
        ?string $comment,
    ): DiseaseReport {
        $diseaseName = $this->meaningfulDiseaseName($diseaseName);
        if ($diseaseName === null) {
            throw new InvalidArgumentException('A confirmed disease name is required before expert confirmation.');
        }

        $from = (string) $report->status;
        $comment = trim((string) $comment);

        return DB::transaction(function () use (
            $report,
            $reviewer,
            $diseaseName,
            $severity,
            $confidenceScore,
            $reasonCode,
            $comment,
            $from
        ): DiseaseReport {
            $payload = [
                'disease_name' => $diseaseName,
                'severity' => $severity,
                'status' => 'confirmed',
            ];

            if ($confidenceScore !== null) {
                $payload['confidence_score'] = max(0.0, min(1.0, $confidenceScore));
            }
            if (Schema::hasColumn('disease_reports', 'verified_by')) {
                $payload['verified_by'] = $reviewer->id;
            }
            if (Schema::hasColumn('disease_reports', 'verified_at')) {
                $payload['verified_at'] = now();
            }
            if (Schema::hasColumn('disease_reports', 'reviewed_by')) {
                $payload['reviewed_by'] = $reviewer->id;
            }
            if (Schema::hasColumn('disease_reports', 'reviewed_at')) {
                $payload['reviewed_at'] = now();
            }
            if (Schema::hasColumn('disease_reports', 'decision_reason_code')) {
                $payload['decision_reason_code'] = $reasonCode;
            }
            if (Schema::hasColumn('disease_reports', 'decision_comment')) {
                $payload['decision_comment'] = $comment !== '' ? $comment : 'Expert confirmed diagnosis.';
            }
            if (Schema::hasColumn('disease_reports', 'scan_metadata')) {
                $payload['scan_metadata'] = $this->mergeVerifiedMetadata(
                    is_array($report->scan_metadata) ? $report->scan_metadata : [],
                    'confirmed',
                    $diseaseName,
                );
            }

            $report->forceFill($payload)->save();
            $this->completeActiveAssignments($report);

            CaseAuditLogger::log(
                'disease_report',
                $report->id,
                'confirm',
                $from,
                'confirmed',
                $comment !== '' ? $comment : 'Expert confirmed diagnosis.',
                [
                    'decision_reason_code' => $reasonCode,
                    'reviewer_user_id' => $reviewer->id,
                    'disease_name' => $diseaseName,
                ],
            );

            return $report->refresh();
        });
    }

    public function reject(
        DiseaseReport $report,
        User $reviewer,
        string $reasonCode,
        ?string $comment,
    ): DiseaseReport {
        $from = (string) $report->status;
        $comment = trim((string) $comment);

        return DB::transaction(function () use ($report, $reviewer, $reasonCode, $comment, $from): DiseaseReport {
            $payload = [
                'status' => 'rejected',
            ];

            if (Schema::hasColumn('disease_reports', 'verified_by')) {
                $payload['verified_by'] = null;
            }
            if (Schema::hasColumn('disease_reports', 'verified_at')) {
                $payload['verified_at'] = null;
            }
            if (Schema::hasColumn('disease_reports', 'reviewed_by')) {
                $payload['reviewed_by'] = $reviewer->id;
            }
            if (Schema::hasColumn('disease_reports', 'reviewed_at')) {
                $payload['reviewed_at'] = now();
            }
            if (Schema::hasColumn('disease_reports', 'decision_reason_code')) {
                $payload['decision_reason_code'] = $reasonCode;
            }
            if (Schema::hasColumn('disease_reports', 'decision_comment')) {
                $payload['decision_comment'] = $comment !== '' ? $comment : 'Expert rejected diagnosis.';
            }
            if (Schema::hasColumn('disease_reports', 'scan_metadata')) {
                $payload['scan_metadata'] = $this->mergeVerifiedMetadata(
                    is_array($report->scan_metadata) ? $report->scan_metadata : [],
                    'rejected',
                    null,
                );
            }

            $report->forceFill($payload)->save();
            $this->completeActiveAssignments($report);

            CaseAuditLogger::log(
                'disease_report',
                $report->id,
                'reject',
                $from,
                'rejected',
                $comment !== '' ? $comment : 'Expert rejected diagnosis.',
                [
                    'decision_reason_code' => $reasonCode,
                    'reviewer_user_id' => $reviewer->id,
                ],
            );

            return $report->refresh();
        });
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function mergeVerifiedMetadata(array $metadata, string $status, ?string $diseaseName): array
    {
        if ($status === 'confirmed' && $diseaseName !== null) {
            $metadata['verified_disease_name'] = $diseaseName;
            $metadata['verified_disease_key'] = $this->normalizeDiseaseKey($diseaseName);
            $metadata['verified_decision_status'] = 'confirmed';
            $metadata['verified_recorded_at'] = now()->toISOString();

            return $metadata;
        }

        unset($metadata['verified_disease_name'], $metadata['verified_disease_key'], $metadata['verified_recorded_at']);
        $metadata['verified_decision_status'] = 'rejected';

        return $metadata;
    }

    private function completeActiveAssignments(DiseaseReport $report): void
    {
        CaseAssignment::query()
            ->where('case_type', 'disease_report')
            ->where('disease_report_id', $report->id)
            ->where('status', 'active')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
    }

    private function meaningfulDiseaseName(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = $this->normalizeDiseaseKey($trimmed);
        if (in_array($normalized, ['pending_analysis', 'analysis_pending', 'pending', 'awaiting_analysis'], true)) {
            return null;
        }

        return $trimmed;
    }

    private function normalizeDiseaseKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = (string) preg_replace('/[\s\-\/()]+/', '_', $normalized);
        $normalized = (string) preg_replace('/[^a-z0-9_]+/', '_', $normalized);

        return trim((string) preg_replace('/_+/', '_', $normalized), '_');
    }
}
