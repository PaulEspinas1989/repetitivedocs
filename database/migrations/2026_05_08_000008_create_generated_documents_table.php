<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->json('variable_values');
            $table->string('file_path')->nullable();
            $table->string('file_name');
            $table->string('disk')->default('documents');
            $table->string('status')->default('ready');
            $table->timestamps();

            $table->index(['workspace_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
