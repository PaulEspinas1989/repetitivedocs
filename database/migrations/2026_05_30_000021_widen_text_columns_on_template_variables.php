<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            // AI-generated descriptions and reasons can exceed varchar(255).
            // Changing these to text avoids SQLSTATE[22001] truncation errors.
            $table->text('description')->nullable()->change();
            $table->text('needs_review_reason')->nullable()->change();
            $table->text('grouping_reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
            $table->string('needs_review_reason')->nullable()->change();
            $table->string('grouping_reason')->nullable()->change();
        });
    }
};
