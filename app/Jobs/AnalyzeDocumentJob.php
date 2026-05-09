<?php

namespace App\Jobs;

use App\Models\Template;
use App\Models\UploadedDocument;
use App\Services\VariableDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Allow up to 10 minutes for Vision-based PDF analysis on large documents.
    public int $timeout = 600;

    // Don't retry — AI analysis is expensive and re-queuing on failure wastes credits.
    public int $tries = 1;

    public function __construct(public readonly UploadedDocument $doc) {}

    public function handle(VariableDetectionService $detector): void
    {
        // File-hash cache: if the same file was already analyzed in this workspace,
        // reuse the existing template instead of running the AI again.
        if (!empty($this->doc->file_hash)) {
            $existing = UploadedDocument::where('workspace_id', $this->doc->workspace_id)
                ->where('file_hash', $this->doc->file_hash)
                ->where('id', '!=', $this->doc->id)
                ->where('status', 'processed')
                ->with('template')
                ->first();

            if ($existing && $existing->template) {
                Log::info('AnalyzeDocumentJob: cache hit — reusing existing template', [
                    'doc_id'          => $this->doc->id,
                    'cached_doc_id'   => $existing->id,
                    'cached_template' => $existing->template->id,
                ]);
                $this->doc->update(['status' => 'processed']);
                return;
            }
        }

        $detector->analyze($this->doc);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AnalyzeDocumentJob failed', [
            'doc_id' => $this->doc->id,
            'error'  => $e->getMessage(),
        ]);
        $this->doc->update(['status' => 'failed']);
    }
}
