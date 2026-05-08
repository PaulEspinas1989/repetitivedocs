<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── variable_occurrences: richer detection and styling metadata ──
        Schema::table('variable_occurrences', function (Blueprint $table) {
            // Where in the document this occurrence was found
            // body | header | footer | table | signature_block | labeled_field | unknown
            $table->string('source_area', 30)->default('unknown')->after('semantic_context');

            // Text of a nearby label (e.g. "Municipal Mayor", "Approved by:")
            $table->text('nearby_label')->nullable()->after('source_area');

            // Casing pattern of the original text
            // uppercase | titlecase | lowercase | mixed
            $table->string('casing_pattern', 20)->default('mixed')->after('nearby_label');

            // How this occurrence was detected
            // pdf_position | ai_occurrence | fallback | user_added
            $table->string('detection_source', 30)->default('pdf_position')->after('casing_pattern');

            $table->index(['template_id', 'source_area']);
        });

        // ── template_variables: surface uncertain AI detections ──
        Schema::table('template_variables', function (Blueprint $table) {
            // True if AI flagged this variable as uncertain / needs human review
            $table->boolean('needs_review')->default(false)->after('ai_suggested');
            $table->text('needs_review_reason')->nullable()->after('needs_review');

            $table->index(['template_id', 'needs_review']);
        });
    }

    public function down(): void
    {
        Schema::table('variable_occurrences', function (Blueprint $table) {
            $table->dropIndex(['template_id', 'source_area']);
            $table->dropColumn(['source_area', 'nearby_label', 'casing_pattern', 'detection_source']);
        });

        Schema::table('template_variables', function (Blueprint $table) {
            $table->dropIndex(['template_id', 'needs_review']);
            $table->dropColumn(['needs_review', 'needs_review_reason']);
        });
    }
};
