<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // If user has no active workspace, assign their first one
        if (!$user->active_workspace_id) {
            $workspace = $user->workspaces()->first();
            if ($workspace) {
                $user->update(['active_workspace_id' => $workspace->id]);
                $user->refresh();
            }
        }

        // Load workspace with plan and share globally with all views
        $workspace = $user->active_workspace_id
            ? $user->workspaces()->with('plan')->find($user->active_workspace_id)
            : null;

        view()->share('currentWorkspace', $workspace);

        return $next($request);
    }
}
