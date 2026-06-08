<?php

namespace App\Services;

use App\Models\CaseAssignment;
use App\Models\DiseaseReport;
use App\Models\SoilHealth;
use App\Models\User;
use App\Support\RegionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CaseAssignmentService
{
    public function autoAssignDiseaseReport(DiseaseReport $report): ?CaseAssignment
    {
        $report->loadMissing('plot.farm');

        return $this->autoAssign(
            case: $report,
            caseType: 'disease_report',
            regionId: $report->plot?->farm?->region_id,
            priority: in_array($report->severity, ['high', 'critical'], true) ? 'high' : 'normal',
            preferredRoles: ['supporter', 'expert'],
            note: 'Automatically assigned to regional supporter for first-line disease triage.',
        );
    }

    public function autoAssignSoilHealth(SoilHealth $soilHealth): ?CaseAssignment
    {
        $soilHealth->loadMissing('plot.farm');

        return $this->autoAssign(
            case: $soilHealth,
            caseType: 'soil_health',
            regionId: $soilHealth->plot?->farm?->region_id,
            priority: $this->soilPriority($soilHealth),
            preferredRoles: ['supporter', 'expert'],
            note: 'Automatically assigned to regional supporter for soil review triage.',
        );
    }

    public function assignDiseaseReport(
        DiseaseReport $report,
        User $assignee,
        User $assignedBy,
        string $priority = 'normal',
        ?string $note = null,
    ): CaseAssignment {
        $report->loadMissing('plot.farm');
        $this->assertAssignable($assignee, $report->plot?->farm?->region_id);

        return DB::transaction(function () use ($report, $assignee, $assignedBy, $priority, $note): CaseAssignment {
            $this->closeActiveAssignments('disease_report', (int) $report->id);

            $assignment = CaseAssignment::query()->create([
                'case_type' => 'disease_report',
                'disease_report_id' => $report->id,
                'soil_health_id' => null,
                'assigned_to_user_id' => $assignee->id,
                'assigned_by_user_id' => $assignedBy->id,
                'priority' => $priority,
                'status' => 'active',
            ]);

            $report->forceFill([
                'escalated_to_user_id' => $assignee->id,
                'escalated_at' => now(),
            ])->save();

            CaseAuditLogger::log(
                'disease_report',
                $report->id,
                'assign',
                $report->status,
                $report->status,
                $note,
                [
                    'assigned_to_user_id' => $assignee->id,
                    'assigned_to_role' => RegionScope::roleName($assignee),
                    'assigned_by_user_id' => $assignedBy->id,
                    'priority' => $priority,
                ],
            );

            return $assignment;
        });
    }

    public function assignSoilHealth(
        SoilHealth $soilHealth,
        User $assignee,
        User $assignedBy,
        string $priority = 'normal',
        ?string $note = null,
    ): CaseAssignment {
        $soilHealth->loadMissing('plot.farm');
        $this->assertAssignable($assignee, $soilHealth->plot?->farm?->region_id);

        return DB::transaction(function () use ($soilHealth, $assignee, $assignedBy, $priority, $note): CaseAssignment {
            $this->closeActiveAssignments('soil_health', (int) $soilHealth->id);

            $assignment = CaseAssignment::query()->create([
                'case_type' => 'soil_health',
                'disease_report_id' => null,
                'soil_health_id' => $soilHealth->id,
                'assigned_to_user_id' => $assignee->id,
                'assigned_by_user_id' => $assignedBy->id,
                'priority' => $priority,
                'status' => 'active',
            ]);

            CaseAuditLogger::log(
                'soil_health',
                $soilHealth->id,
                'assign',
                (string) $soilHealth->review_status,
                (string) $soilHealth->review_status,
                $note,
                [
                    'assigned_to_user_id' => $assignee->id,
                    'assigned_to_role' => RegionScope::roleName($assignee),
                    'assigned_by_user_id' => $assignedBy->id,
                    'priority' => $priority,
                ],
            );

            return $assignment;
        });
    }

    /**
     * @return array<int, string>
     */
    public function assignableReviewerOptions(User $actor, ?int $regionId, array $roles = ['supporter', 'expert']): array
    {
        return $this->reviewerCandidates($regionId, $roles)
            ->filter(function (User $candidate) use ($actor, $regionId): bool {
                if (! RegionScope::isSuperAdmin($actor) && ! RegionScope::canAccessRegion($actor, $regionId)) {
                    return false;
                }

                return $this->canReviewerCoverRegion($candidate, $regionId);
            })
            ->mapWithKeys(function (User $user): array {
                $role = ucfirst(str_replace('_', ' ', RegionScope::roleName($user)));
                $region = $user->region?->name ? ' - '.$user->region->name : '';

                return [$user->id => "{$user->name} ({$role}{$region})"];
            })
            ->all();
    }

    private function autoAssign(
        Model $case,
        string $caseType,
        ?int $regionId,
        string $priority,
        array $preferredRoles,
        string $note,
    ): ?CaseAssignment {
        if ($this->hasActiveAssignment($caseType, (int) $case->getKey())) {
            return null;
        }

        $assignee = $this->bestReviewerForRegion($regionId, $preferredRoles);
        if (! $assignee instanceof User) {
            Log::warning('Case left unassigned: no active reviewer found for region', [
                'case_type' => $caseType,
                'case_id' => $case->getKey(),
                'region_id' => $regionId,
            ]);

            return null;
        }

        $actor = $this->systemAssignmentActor() ?? $assignee;

        return $caseType === 'soil_health'
            ? $this->assignSoilHealth($case, $assignee, $actor, $priority, $note)
            : $this->assignDiseaseReport($case, $assignee, $actor, $priority, $note);
    }

    private function bestReviewerForRegion(?int $regionId, array $preferredRoles): ?User
    {
        return $this->reviewerCandidates($regionId, $preferredRoles)
            ->map(function (User $candidate) use ($regionId, $preferredRoles): array {
                $role = RegionScope::roleName($candidate);
                $activeAssignments = CaseAssignment::query()
                    ->where('assigned_to_user_id', $candidate->id)
                    ->where('status', 'active')
                    ->count();

                return [
                    'user' => $candidate,
                    'distance' => $regionId === null ? PHP_INT_MAX : RegionScope::scopeMatchDistance($candidate, (int) $regionId),
                    'role_priority' => array_search($role, $preferredRoles, true),
                    'active_assignments' => $activeAssignments,
                ];
            })
            ->filter(fn (array $item): bool => $item['distance'] !== null && $item['role_priority'] !== false)
            ->sort(function (array $a, array $b): int {
                return ($a['distance'] <=> $b['distance'])
                    ?: ($a['role_priority'] <=> $b['role_priority'])
                    ?: ($a['active_assignments'] <=> $b['active_assignments'])
                    ?: ($a['user']->id <=> $b['user']->id);
            })
            ->pluck('user')
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    private function reviewerCandidates(?int $regionId, array $roles): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('name', $roles))
            ->with(['role', 'region', 'scopedRegions'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $candidate): bool => $this->canReviewerCoverRegion($candidate, $regionId))
            ->values();
    }

    private function canReviewerCoverRegion(User $candidate, ?int $regionId): bool
    {
        if ($regionId === null) {
            return RegionScope::isBackoffice($candidate);
        }

        return RegionScope::scopeMatchDistance($candidate, (int) $regionId) !== null;
    }

    private function assertAssignable(User $assignee, ?int $regionId): void
    {
        if (! in_array(RegionScope::roleName($assignee), ['supporter', 'expert'], true)) {
            throw new \InvalidArgumentException('Cases can only be assigned to active supporters or experts.');
        }

        if (! $assignee->is_active || ! $this->canReviewerCoverRegion($assignee, $regionId)) {
            throw new \InvalidArgumentException('Selected reviewer is outside the case region scope.');
        }
    }

    private function hasActiveAssignment(string $caseType, int $caseId): bool
    {
        return CaseAssignment::query()
            ->where('case_type', $caseType)
            ->when(
                $caseType === 'soil_health',
                fn ($query) => $query->where('soil_health_id', $caseId),
                fn ($query) => $query->where('disease_report_id', $caseId),
            )
            ->where('status', 'active')
            ->exists();
    }

    private function closeActiveAssignments(string $caseType, int $caseId): void
    {
        CaseAssignment::query()
            ->where('case_type', $caseType)
            ->when(
                $caseType === 'soil_health',
                fn ($query) => $query->where('soil_health_id', $caseId),
                fn ($query) => $query->where('disease_report_id', $caseId),
            )
            ->where('status', 'active')
            ->update([
                'status' => 'reassigned',
                'completed_at' => now(),
            ]);
    }

    private function soilPriority(SoilHealth $soilHealth): string
    {
        $ph = $soilHealth->ph_level !== null ? (float) $soilHealth->ph_level : null;
        $moisture = $soilHealth->moisture_level !== null ? (float) $soilHealth->moisture_level : null;

        if (($ph !== null && ($ph < 5.0 || $ph > 8.0)) || ($moisture !== null && ($moisture < 15 || $moisture > 85))) {
            return 'high';
        }

        if (($ph !== null && ($ph < 5.5 || $ph > 7.5)) || ($moisture !== null && ($moisture < 25 || $moisture > 75))) {
            return 'normal';
        }

        return 'low';
    }

    private function systemAssignmentActor(): ?User
    {
        $current = auth()->user();
        if ($current instanceof User) {
            return $current;
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'super_admin'))
            ->orderBy('id')
            ->first();
    }
}
