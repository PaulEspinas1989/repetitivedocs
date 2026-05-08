<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variable_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_variable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('workspace_id');

            // Where in the document
            $table->unsignedSmallInteger('page_number')->nullable();

            // The text as it appears in the original document
            $table->text('original_text');
            $table->text('normalized_text')->nullable();    // lowercased, stripped of honorifics
            $table->string('prefix_text', 120)->nullable(); // "HON.", "Mayor", "Approved by:"
            $table->string('suffix_text', 120)->nullable(); // ", MBA", title below

            // Surrounding context for AI reasoning
            $table->text('context_before')->nullable();
            $table->text('context_after')->nullable();
            $table->string('section_label', 120)->nullable(); // "APPROVAL", "SIGNATORY", "HEADER"
            $table->string('semantic_context', 60)->nullable(); // signature_block, labeled_field, header, footer, body

            // PDF overlay coordinates (percentage of page dimensions)
            $table->json('bounding_box')->nullable();
            // style_snapshot: font_size, font_color, font_family, text_align, font_weight
            $table->json('style_snapshot')->nullable();

            // How the value should be inserted here
            $table->string('replacement_strategy', 80)->default('replace_exact_text_preserve_style');
            // 0–100 integer confidence
            $table->unsignedTinyInteger('confidence_pct')->default(100);
            // active | ignored | unlinked | needs_review
            $table->string('status', 20)->default('active');
            $table->text('ai_reason')->nullable();

            $table->timestamps();

            $table->index(['template_variable_id', 'status']);
            $table->index(['template_id', 'page_number']);
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variable_occurrences');
    }
};
