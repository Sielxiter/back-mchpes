<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CandidatureDocument;
use App\Services\SecureFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentAdminController extends Controller
{
    public function __construct(protected SecureFileUploadService $uploadService)
    {
    }

    public function download(Request $request, CandidatureDocument $document): BinaryFileResponse|JsonResponse
    {
        try {
            $file = $this->uploadService->getFileInfoForDownload($document);

            return response()->download(
                $file['full_path'],
                $file['original_name'],
                ['Content-Type' => $file['mime_type']]
            );
        } catch (\Exception $e) {
            Log::error('Admin document download failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $document->id,
            ]);

            return response()->json(['error' => 'Fichier non accessible: ' . $e->getMessage()], 500);
        }
    }
}
