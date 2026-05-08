<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            // Canonical grouping: multiple variables can point to the same canonical one
            $table->unsignedBigInteger('canonical_variable_id')->nullable()->after('id');
            // Semantic typing for grouping logic
            $table->string('semantic_type', 60)->nullable()->after('type');        // person_name, org_name, date, currency, etc.
            $table->string('entity_role', 60)->nullable()->after('semantic_type'); // mayor_signatory, company, recipient, etc.
            // AI grouping confidence (0-100)
            $table->unsignedTinyInteger('grouping_confidence')->nullable()->after('entity_role');
            // Reason AI grouped these occurrences together
            $table->text('grouping_reason')->nullable()->after('grouping_confidence');
        });

        Schema::table('template_variables', function (Blueprint $table) {
            $table->foreign('canonical_variable_id')
                  ->references('id')
                  ->on('template_variables')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->dropForeign(['canonical_variable_id']);
            $table->dropColumn(['canonical_variable_id', 'semantic_type', 'entity_role', 'grouping_confidence', 'grouping_reason']);
        });
    }
};
