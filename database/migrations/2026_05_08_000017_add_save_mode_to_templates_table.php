<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // saved_template  — in library, reusable
            // one_time        — temporary, not in library
            // draft           — existing status (AI scan complete, not yet reviewed)
            $table->string('save_mode', 20)->default('draft')->after('status');

            // True once user explicitly saves as a reusable template
            $table->boolean('is_saved_template')->default(false)->after('save_mode');
            $table->timestamp('saved_at')->nullable()->after('is_saved_template');
            $table->unsignedBigInteger('saved_by_user_id')->nullable()->after('saved_at');

            // True once the user has gone through Fixed Fields Review after first generation
            $table->boolean('fixed_fields_reviewed')->default(false)->after('saved_by_user_id');
            $table->timestamp('fixed_fields_reviewed_at')->nullable()->after('fixed_fields_reviewed');

            $table->index(['workspace_id', 'is_saved_template']);
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'is_saved_template']);
            $table->dropColumn([
                'save_mode', 'is_saved_template', 'saved_at', 'saved_by_user_id',
                'fixed_fields_reviewed', 'fixed_fields_reviewed_at',
            ]);
        });
    }
};
