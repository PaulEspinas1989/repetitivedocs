<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-field value traceability for generated documents.
     * Records what value was actually used for each variable in each generation,
     * and where that value came from. Past records never change.
     */
    public function up(): void
    {
        Schema::create('generated_document_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_variable_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('workspace_id');

            // The value that actually ended up in the document
            $table->text('final_value_used')->nullable();

            // What the user submitted (may differ from final if fixed overrode it)
            $table->text('submitted_value')->nullable();

            // Where the final value came from
            // user_input | fixed_value | default_value | one_time_override
            // | portal_submission | bulk_row | system_generated
            $table->string('value_source', 40)->default('user_input');

            // Snapshot of what the variable's mode was at generation time
            // (so history stays accurate even if user later changes modes)
            $table->string('value_mode_at_generation', 20)->default('ask_each_time');

            $table->boolean('was_fixed_at_generation')->default(false);
            $table->boolean('was_default_at_generation')->default(false);

            $table->timestamps();

            $table->index(['generated_document_id']);
            $table->index(['template_variable_id', 'value_source']);
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_document_values');
    }
};
