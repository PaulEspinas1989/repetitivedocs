<?php

namespace App\Http\Controllers;

use App\Models\UploadedDocument;
use App\Services\VariableDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentAnalysisController extends Controller
{
    public function __construct(private VariableDetectionService $detector) {}

    public function analyze(Request $request, UploadedDocument $document): JsonResponse
    {
        // Ensure the document belongs to the current workspace
        $workspace = auth()->user()->activeWorkspace;

        if ((int) $document->workspace_id !== (int) auth()->user()->active_workspace_id) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        // If already processed, return the existing template
        if ($document->status === 'processed') {
            $template = $document->template()->first();
            if ($template) {
                return response()->json([
                    'success'     => true,
                    'template_id' => $template->id,
                    'redirect'    => route('automation-map', $template->id),
                ]);
            }
        }

        if ($document->status === 'processing') {
            return response()->json(['status' => 'processing'], 202);
        }

        try {
            $template = $this->detector->analyze($document);

            return response()->json([
                'success'     => true,
                'template_id' => $template->id,
                'redirect'    => route('automation-map', $template->id),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DocumentAnalysis failed', [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error'   => true,
                'message' => 'Analysis failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
