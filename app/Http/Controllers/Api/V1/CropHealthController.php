<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiseaseReport;
use App\Support\RegionScope;
use Illuminate\Http\Request;

class CropHealthController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', DiseaseReport::class);

        $user = $request->user();
        $query = DiseaseReport::query()
            ->with(['crop', 'plot'])
            ->orderBy('reported_at', 'desc');

        if (RegionScope::roleName($user) === 'farmer') {
            $query->whereHas('plot.farm', fn ($farmQuery) => $farmQuery->where('farmer_id', $user->id));
        } else {
            $regionIds = RegionScope::accessibleRegionIds($user);
            if ($regionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas(
                    'plot.farm',
                    fn ($farmQuery) => $farmQuery->whereIn('region_id', $regionIds)
                );
            }
        }

        $reports = $query->paginate($this->perPage($request));

        $reports->through(function (DiseaseReport $report): array {
            return [
                'crop_id' => $report->crop_id,
                'crop_name' => $report->crop?->name,
                'plot_id' => $report->plot_id,
                'plot_name' => $report->plot?->plot_name,
                'status' => $report->severity ?? $report->status,
                'scanned_at' => optional($report->reported_at)->toISOString()
                    ?? optional($report->created_at)->toISOString(),
            ];
        });

        return response()->json($reports);
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 15);

        return max(1, min($perPage, 100));
    }
}
