<?php

namespace App\Http\Controllers;

use App\Models\UploadedDocument;
use Illuminate\Http\JsonResponse;

class DocumentAnalysisController extends Controller
{
    /**
     * Status-check endpoint polled by the ai-loading page.
     *
     * The actual analysis now runs in AnalyzeDocumentJob (queued worker).
     * This endpoint just reads the current doc status from the database —
     * no AI calls, no heavy work, no timeouts.
     */
    public function analyze(UploadedDocument $document): JsonResponse
    {
        if ((int) $document->workspace_id !== (int) auth()->user()->active_workspace_id) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        return match ($document->status) {
            'processed' => $this->respondProcessed($document),
            'failed'    => response()->json([
                'error'   => true,
                'message' => 'Analysis failed. Please try uploading again.',
            ], 500),
            default     => response()->json(['status' => 'processing'], 202),
        };
    }

    private function respondProcessed(UploadedDocument $document): JsonResponse
    {
        $template = $document->template()->first();

        if (!$template) {
            // Job finished but template wasn't created — treat as failed
            return response()->json([
                'error'   => true,
                'message' => 'Analysis completed but no template was created. Please try again.',
            ], 500);
        }

        return response()->json([
            'success'     => true,
            'template_id' => $template->id,
            'redirect'    => route('automation-map', $template->id),
        ]);
    }
}
