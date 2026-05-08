<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->unsignedInteger('template_limit')->nullable();
            $table->unsignedInteger('document_limit')->nullable();
            $table->unsignedInteger('ai_credit_limit')->nullable();
            $table->unsignedInteger('file_size_limit_mb')->default(5);
            $table->unsignedInteger('bulk_generation_limit')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('storage_days')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
