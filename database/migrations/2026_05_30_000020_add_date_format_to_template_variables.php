<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            // Stores the PHP date format string detected from the original document,
            // e.g. 'F j, Y' for "May 30, 2026", 'd/m/Y' for "30/05/2026".
            // Used by DateFormatterService to format ISO input from <input type="date">
            // into the original document's expected date presentation.
            $table->string('date_format', 30)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->dropColumn('date_format');
        });
    }
};
