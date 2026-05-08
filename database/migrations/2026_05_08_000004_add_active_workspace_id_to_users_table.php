<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_workspace_id')
                  ->nullable()
                  ->after('email_verified_at')
                  ->constrained('workspaces')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_workspace_id']);
            $table->dropColumn('active_workspace_id');
        });
    }
};
