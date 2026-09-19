<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from_month');
            $table->unsignedBigInteger('total_limit_cents')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'effective_from_month']);
        });

        Schema::create('budget_template_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('limit_cents')->default(0);
            $table->timestamps();

            $table->unique(['budget_template_id', 'category_id']);
        });

        Schema::create('monthly_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('total_limit_cents')->default(0);
            $table->foreignId('source_template_id')->nullable()->constrained('budget_templates')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'month']);
        });

        Schema::create('monthly_budget_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monthly_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('limit_cents')->default(0);
            $table->timestamps();

            $table->unique(['monthly_budget_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_budget_limits');
        Schema::dropIfExists('monthly_budgets');
        Schema::dropIfExists('budget_template_limits');
        Schema::dropIfExists('budget_templates');
    }
};
