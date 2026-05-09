<?php

namespace App\Console\Commands;

use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;

class DeleteUserTemplates extends Command
{
    protected $signature   = 'templates:delete-for-user {email}';
    protected $description = 'Delete all templates belonging to workspaces of a given user email';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user  = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: {$email}");
            return 1;
        }

        $wids  = Workspace::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
        $count = Template::whereIn('workspace_id', $wids)->delete();

        $this->info("Deleted {$count} templates for {$email}");
        return 0;
    }
}
