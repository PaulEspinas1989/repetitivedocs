<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Missing index on template_variables.approval_status — this column is
        // filtered in almost every query (pending/approved/rejected).
        Schema::table('template_variables', function (Blueprint $table) {
            $table->index('approval_status');
        });

        // Prevent duplicate occurrence records for the same variable on the same page
        // at the same bounding-box position (protects against retried analysis requests).
        // Uses a partial unique index via a DB statement since Eloquent can't do expression indexes.
        // The index is on (template_variable_id, page_number) so that per-page uniqueness
        // is enforced at the application level rather than full JSON equality.
        Schema::table('variable_occurrences', function (Blueprint $table) {
            $table->index(['template_variable_id', 'page_number'], 'vo_variable_page_idx');
        });
    }

    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
        });

        Schema::table('variable_occurrences', function (Blueprint $table) {
            $table->dropIndex('vo_variable_page_idx');
        });
    }
};
