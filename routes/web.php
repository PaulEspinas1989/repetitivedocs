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
        return view('ai-loading', ['document' => $document]);
    })->name('ai-loading');

    // ── Document Analysis (AJAX) ──────────────────────────────
    Route::post('/documents/{document}/analyze', [DocumentAnalysisController::class, 'analyze'])
         ->name('documents.analyze');

    // ── Automation Map ────────────────────────────────────────
    Route::get('/templates/{template}/automation-map', function (Template $template) {
        $template->load('variables');
        return view('automation-map', compact('template'));
    })->name('automation-map');

    // ── Template: approve all variables ──────────────────────
    Route::post('/templates/{template}/approve-all', function (Template $template) {
        $workspaceIds = auth()->user()->workspaces()->pluck('workspaces.id')->toArray();
        if (!in_array($template->workspace_id, $workspaceIds)) {
            abort(403);
        }
        $template->variables()->update(['approval_status' => 'approved']);
        return redirect()->route('templates.editor', $template->id);
    })->name('templates.approve-all');

    // ── Group approve/reject (repeating or standalone) ────────
    Route::post('/templates/{template}/group-action', function (Template $template) {
        $workspaceIds = auth()->user()->workspaces()->pluck('workspaces.id')->toArray();
        if (!in_array($template->workspace_id, $workspaceIds)) {
            abort(403);
        }
        $group  = request('group');  // 'repeating' or 'standalone'
        $action = request('action'); // 'approve' or 'reject'
        $status = $action === 'approve' ? 'approved' : 'rejected';

        $query = $template->variables();
        if ($group === 'repeating') {
            $query->where('occurrences', '>', 1);
        } elseif ($group === 'standalone') {
            $query->where('occurrences', 1);
        }
        $query->update(['approval_status' => $status]);

        return back();
    })->name('templates.group-action');

    // ── Template variable review ──────────────────────────────
    Route::get('/templates/{template}/variables', function (Template $template) {
        $template->load('variables');
        return view('automation-map', compact('template'));
    })->name('templates.variables');

    // ── Template editor ───────────────────────────────────────
    Route::get('/templates/{template}/editor',   [TemplateEditorController::class, 'show'])->name('templates.editor');
    Route::post('/templates/{template}/approve-all-editor', [TemplateEditorController::class, 'approveAll'])->name('templates.editor.approve-all');

    // ── Variable actions ──────────────────────────────────────
    Route::post('/templates/{template}/variables/{variable}/approve', [TemplateEditorController::class, 'approveVariable'])->name('templates.variables.approve');
    Route::post('/templates/{template}/variables/{variable}/reject',  [TemplateEditorController::class, 'rejectVariable'])->name('templates.variables.reject');
    Route::patch('/templates/{template}/variables/{variable}',        [TemplateEditorController::class, 'updateVariable'])->name('templates.variables.update');

    // ── Fillable form ─────────────────────────────────────────
    Route::get('/templates/{template}/form',  [FillableFormController::class, 'show'])->name('fillable-form');
    Route::post('/templates/{template}/form', [FillableFormController::class, 'generate'])->name('fillable-form.generate');

    // ── Generation result ─────────────────────────────────────
    Route::get('/generated/{generated}', function (GeneratedDocument $generated) {
        $generated->load(['template.approvedVariables']);
        return view('generation-result', compact('generated'));
    })->name('generation-result');

    // ── Download generated document ───────────────────────────
    Route::get('/generated/{generated}/download', [FillableFormController::class, 'download'])->name('generated-documents.download');
});
