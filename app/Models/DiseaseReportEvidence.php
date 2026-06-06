<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DiseaseReportEvidence extends Model
{
    protected $fillable = [
        'disease_report_id',
        'uploaded_by',
        'kind',
        'file_path',
        'file_disk',
        'mime_type',
        'size_bytes',
        'caption',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function diseaseReport()
    {
        return $this->belongsTo(DiseaseReport::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }


    public function temporaryUrl(?Request $request = null, int $minutes = 60): ?string
    {
        if (! filled($this->file_path) || ! $this->diseaseReport) {
            return null;
        }

        return $this->diseaseReport->temporarySignedApiUrl(
            'api.v1.disease-reports.media.evidence',
            [
                'report' => $this->disease_report_id,
                'evidence' => $this->getKey(),
            ],
            $request,
            $minutes,
        );
    }

    public function authenticatedUrl(?Request $request = null): ?string
    {
        if (! filled($this->file_path) || ! $this->diseaseReport) {
            return null;
        }

        return $this->diseaseReport->authenticatedApiUrl(
            'api.v1.disease-reports.media.evidence.authenticated',
            [
                'report' => $this->disease_report_id,
                'evidence' => $this->getKey(),
            ],
            $request,
        );
    }

    public function backofficePreviewSrc(): ?string
    {
        $disk = (string) ($this->file_disk ?: 'public');
        $path = (string) ($this->file_path ?: '');
        if (trim($path) === '' || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $resolvedMime = trim((string) $this->mime_type);
        if ($resolvedMime === '') {
            $resolvedMime = (string) (Storage::disk($disk)->mimeType($path) ?: '');
        }

        if (! str_starts_with(strtolower($resolvedMime), 'image/')) {
            return null;
        }

        return 'data:'.$resolvedMime.';base64,'.base64_encode(Storage::disk($disk)->get($path));
    }
}
