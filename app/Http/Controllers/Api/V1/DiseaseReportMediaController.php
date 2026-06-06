<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiseaseReport;
use App\Models\DiseaseReportEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DiseaseReportMediaController extends Controller
{
    public function originalAuthenticated(Request $request, DiseaseReport $report)
    {
        $this->authorize('view', $report);

        return $this->inlineResponse(
            (string) ($report->image_disk ?: 'public'),
            (string) ($report->image_path ?: ''),
            $report->image_mime,
        );
    }

    public function original(Request $request, DiseaseReport $report)
    {
        return $this->inlineResponse(
            (string) ($report->image_disk ?: 'public'),
            (string) ($report->image_path ?: ''),
            $report->image_mime,
        );
    }

    public function evidenceAuthenticated(Request $request, DiseaseReport $report, DiseaseReportEvidence $evidence)
    {
        $this->authorize('view', $report);
        abort_unless((int) $evidence->disease_report_id === (int) $report->id, 404);

        return $this->inlineResponse(
            (string) ($evidence->file_disk ?: 'public'),
            (string) ($evidence->file_path ?: ''),
            $evidence->mime_type,
        );
    }

    public function evidence(Request $request, DiseaseReport $report, DiseaseReportEvidence $evidence)
    {
        abort_unless((int) $evidence->disease_report_id === (int) $report->id, 404);

        return $this->inlineResponse(
            (string) ($evidence->file_disk ?: 'public'),
            (string) ($evidence->file_path ?: ''),
            $evidence->mime_type,
        );
    }

    private function inlineResponse(string $disk, string $path, ?string $mime)
    {
        abort_if(trim($path) === '', 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response(
            $path,
            basename($path),
            [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
                'Cache-Control' => 'private, max-age=300',
            ],
        );
    }
}
