<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class FillableFormController extends Controller
{
    public function __construct(private DocumentGenerationService $generator) {}

    // Return type is View|RedirectResponse — redirect fires if no approved variables
    public function show(Template $template): View|RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $template->load(['approvedVariables']);

        if ($template->approvedVariables->isEmpty()) {
            return redirect()->route('templates.editor', $template->id)
                ->with('toast', 'Approve at least one variable before generating.');
        }

        return view('fillable-form', compact('template'));
    }

    public function generate(Request $request, Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        // approvedVariables() already orders by sort_order — no closure needed here.
        // The generator service reloads with activeOccurrences for the PDF path.
        $template->load(['approvedVariables']);

        // Build validation rules dynamically from approved variable types
        $rules = [];
        foreach ($template->approvedVariables as $var) {
            $rule = $var->is_required ? ['required', 'string', 'max:500'] : ['nullable', 'string', 'max:500'];
            if ($var->type === 'email') {
                $rule[] = 'email';
            } elseif ($var->type === 'date') {
                $rule = $var->is_required ? ['required', 'date'] : ['nullable', 'date'];
            } elseif ($var->type === 'number') {
                $rule = $var->is_required ? ['required', 'numeric'] : ['nullable', 'numeric'];
            }
            // currency stays as string — user may enter "1,000,000" with commas
            $rules['fields.' . $var->name] = $rule;
        }

        $validated = $request->validate($rules);
        $values    = $validated['fields'] ?? [];

        // Strip peso sign and commas from currency fields so generation stores plain numbers
        foreach ($template->approvedVariables as $var) {
            if ($var->type === 'currency' && isset($values[$var->name])) {
                $values[$var->name] = preg_replace('/[₱,\s]/', '', $values[$var->name]);
            }
        }

        try {
            $generated = $this->generator->generate($template, $values);
            return redirect()->route('generation-result', $generated->id);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Document generation failed: ' . $e->getMessage());
        }
    }

    public function download(GeneratedDocument $generated): StreamedResponse
    {
        $this->authorizeGeneratedDocument($generated);

        return Storage::disk($generated->disk)->download(
            $generated->file_path,
            $generated->file_name
        );
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function authorizeWorkspace(Template $template): void
    {
        $ids = auth()->user()->workspaces()->pluck('workspaces.id')->toArray();
        if (!in_array($template->workspace_id, $ids)) {
            abort(403);
        }
    }

    private function authorizeGeneratedDocument(GeneratedDocument $document): void
    {
        $ids = auth()->user()->workspaces()->pluck('workspaces.id')->toArray();
        if (!in_array($document->workspace_id, $ids)) {
            abort(403);
        }
    }
}
