<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $templates = Template::where('workspace_id', auth()->user()->active_workspace_id)
            ->withCount('approvedVariables')
            ->latest()
            ->get();

        return view('dashboard', compact('templates'));
    }

    public function destroy(Template $template): RedirectResponse
    {
        if ((int) $template->workspace_id !== (int) auth()->user()->active_workspace_id) {
            abort(403);
        }

        $template->delete();

        return redirect()->route('dashboard')->with('toast', 'Template deleted.');
    }
}
