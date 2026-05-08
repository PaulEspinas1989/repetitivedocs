<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->unsignedSmallInteger('occurrences')->default(1)->after('text_positions');
        });

        // Backfill existing rows that got NULL when the column was added
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE template_variables SET occurrences = 1 WHERE occurrences IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->dropColumn('occurrences');
        });
    }
};
