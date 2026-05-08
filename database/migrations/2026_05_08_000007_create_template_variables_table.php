<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('type')->default('text');
            $table->string('description')->nullable();
            $table->string('example_value')->nullable();
            $table->string('default_value')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('approval_status')->default('pending');
            $table->boolean('ai_suggested')->default(true);
            $table->timestamps();

            $table->index(['template_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_variables');
    }
};
