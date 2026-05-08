<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAnalysisController;
use App\Http\Controllers\FillableFormController;
use App\Http\Controllers\TemplateEditorController;
use App\Http\Controllers\UploadController;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\UploadedDocument;
use Illuminate\Support\Facades\Route;

// ── Public landing page ───────────────────────────────────────
Route::get('/', function () {
    return view('landing');
})->name('home');

// ── Guest-only auth routes ────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/register',  [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/forgot-password',  [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password',        [AuthController::class, 'resetPassword'])->name('password.update');
});

// ── Authenticated routes ──────────────────────────────────────
Route::middleware(['auth', 'workspace'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Upload ────────────────────────────────────────────────
    Route::get('/upload',  [UploadController::class, 'show'])->name('upload');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');

    // ── AI Loading ────────────────────────────────────────────
    Route::get('/ai-loading/{document}', function (UploadedDocument $document) {
        if ((int) $document->workspace_id !== (int) auth()->user()->active_workspace_id) {
            abort(403);
        }
        return view('ai-loading', ['document' => $document]);
    })->name('ai-loading');

    // ── Document Analysis (AJAX) ──────────────────────────────
    Route::post('/documents/{document}/analyze', [DocumentAnalysisController::class, 'analyze'])
         ->name('documents.analyze');

    // ── Automation Map ────────────────────────────────────────
    Route::get('/templates/{template}/automation-map', function (Template $template) {
        if ((int) $template->workspace_id !== (int) auth()->user()->active_workspace_id) {
            abort(403);
        }
        $template->load(['variables' => fn($q) => $q->orderBy('sort_order')
            ->select(['id','template_id','workspace_id','name','label','type','description',
                      'example_value','approval_status','occurrences','is_required','sort_order','ai_suggested'])
        ]);
        return view('automation-map', compact('template'));
    })->name('automation-map');

    // ── Template: approve all variables ──────────────────────
    Route::post('/templates/{template}/approve-all', function (Template $template) {
        if ((int) $template->workspace_id !== (int) auth()->user()->active_workspace_id) {
            abort(403);
        }
        $template->variables()->update(['approval_status' => 'approved']);
        return redirect()->route('templates.editor', $template->id)
            ->with('toast', 'All fields approved. Ready to generate your form.');
    })->name('templates.approve-all');

    // ── Group approve/reject (repeating or standalone) ────────
    Route::post('/templates/{template}/group-action', function (Template $template) {
        if ((int) $template->workspace_id !== (int) auth()->user()->active_workspace_id) {
            abort(403);
        }

        $group  = request('group');
        $action = request('action');

        // Strict validation — reject anything unexpected
        if (!in_array($group, ['repeating', 'standalone'], true)) {
            abort(422, 'Invalid group.');
        }
        if (!in_array($action, ['approve', 'reject'], true)) {
            abort(422, 'Invalid action.');
        }

        $status = $action === 'approve' ? 'approved' : 'rejected';

        $query = $template->variables();
        if ($group === 'repeating') {
            $query->where('occurrences', '>', 1);
        } else {
            $query->where('occurrences', '<=', 1);
        }
        $query->update(['approval_status' => $status]);

        $groupLabel  = $group === 'repeating' ? 'Repeating' : 'Standalone';
        $actionLabel = $action === 'approve' ? 'approved' : 'rejected';

        return redirect()->route('automation-map', $template->id)
            ->with('toast', "{$groupLabel} fields {$actionLabel}.");
    })->name('templates.group-action');

    // ── Template variable review ──────────────────────────────
    Route::get('/templates/{template}/variables', function (Template $template) {
        if ((int) $template->workspace_id !== (int) auth()->user()->active_workspace_id) {
            abort(403);
        }
        $template->load(['variables' => fn($q) => $q->orderBy('sort_order')
            ->select(['id','template_id','workspace_id','name','label','type','description',
                      'example_value','approval_status','occurrences','is_required','sort_order','ai_suggested'])
        ]);
        return view('automation-map', compact('template'));
    })->name('templates.variables');

    // ── Template editor ───────────────────────────────────────
    Route::get('/templates/{template}/editor',   [TemplateEditorController::class, 'show'])->name('templates.editor');
    Route::post('/templates/{template}/approve-all-editor', [TemplateEditorController::class, 'approveAll'])->name('templates.editor.approve-all');

    // ── Variable actions ──────────────────────────────────────
    Route::post('/templates/{template}/variables/{variable}/approve', [TemplateEditorController::class, 'approveVariable'])->name('templates.variables.approve');
    Route::post('/templates/{template}/variables/{variable}/reject',  [TemplateEditorController::class, 'rejectVariable'])->name('templates.variables.reject');
    Route::post('/templates/{template}/variables/{variable}/undo',    [TemplateEditorController::class, 'undoVariable'])->name('templates.variables.undo');
    Route::patch('/templates/{template}/variables/{variable}',        [TemplateEditorController::class, 'updateVariable'])->name('templates.variables.update');

    // ── Fillable form ─────────────────────────────────────────
    Route::get('/templates/{template}/form',  [FillableFormController::class, 'show'])->name('fillable-form');
    Route::post('/templates/{template}/form', [FillableFormController::class, 'generate'])->name('fillable-form.generate');

    // ── Generation result ─────────────────────────────────────
    Route::get('/generated/{generated}', function (GeneratedDocument $generated) {
        if ((int) $generated->workspace_id !== (int) auth()->user()->active_workspace_id) {
            abort(403);
        }
        $generated->load(['template.approvedVariables']);
        return view('generation-result', compact('generated'));
    })->name('generation-result');

    // ── Download generated document ───────────────────────────
    Route::get('/generated/{generated}/download', [FillableFormController::class, 'download'])->name('generated-documents.download');
});
