<?php

namespace App\Http\Controllers;

use App\Models\UploadedDocument;
use App\Services\PdfToDocxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UploadController extends Controller
{
    public function show(): View
    {
        return view('upload');
    }

    public function __construct(private PdfToDocxService $pdfConverter) {}

    public function store(Request $request): RedirectResponse
    {
        $workspace = auth()->user()->activeWorkspace()->with('plan')->first();
        $limitMb   = $workspace?->plan?->file_size_limit_mb ?? 5;
        $limitKb   = $limitMb * 1024;

        $request->validate([
            'document'      => ['required', 'file', 'mimes:pdf,doc,docx', "max:{$limitKb}"],
            'template_name' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'in:Proposal,Contract,Certificate,HR Letter,Government Letter,Invoice,Other'],
        ], [
            'document.max'   => "Your plan allows files up to {$limitMb} MB. Please upgrade to upload larger files.",
            'document.mimes' => 'Only PDF and Word documents (DOC, DOCX) are supported.',
        ]);

        $file       = $request->file('document');
        $uuid       = (string) Str::uuid();
        $ext        = strtolower($file->getClientOriginalExtension());
        $storedName = $uuid . '.' . $ext;
        $path       = 'workspaces/' . $workspace->id . '/uploads/' . $storedName;

        Storage::disk('documents')->put($path, file_get_contents($file->getRealPath()));

        $isPdf    = strtolower($ext) === 'pdf';
        $mimeType = $file->getMimeType();
        $finalExt = $ext;

        // If PDF and LibreOffice is available, convert to DOCX for format-preserving generation
        if ($isPdf && $this->pdfConverter->isAvailable()) {
            $convertedPath = $this->pdfConverter->convert($path, $workspace->id);
            if ($convertedPath) {
                $path      = $convertedPath;
                $storedName = basename($convertedPath);
                $finalExt   = 'docx';
                $mimeType   = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
        }

        $doc = UploadedDocument::create([
            'workspace_id'  => $workspace->id,
            'user_id'       => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'stored_name'   => $storedName,
            'disk'          => 'documents',
            'path'          => $path,
            'mime_type'     => $mimeType,
            'size_bytes'    => $file->getSize(),
            'file_hash'     => hash_file('sha256', $file->getRealPath()),
            'template_name' => $request->template_name,
            'document_type' => $request->document_type,
            'status'        => 'pending',
        ]);

        return redirect()->route('ai-loading', $doc->id);
    }
}
