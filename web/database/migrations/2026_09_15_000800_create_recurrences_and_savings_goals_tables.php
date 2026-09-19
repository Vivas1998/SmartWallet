<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedBigInteger('target_amount_cents');
            $table->date('target_date')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'archived_at']);
        });

        Schema::create('recurrence_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['expense', 'income', 'transfer', 'investment_contribution']);
            $table->unsignedBigInteger('amount_cents');
            $table->string('concept', 180);
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('savings_goal_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'annual']);
            $table->date('start_on');
            $table->unsignedTinyInteger('anchor_day');
            $table->date('next_occurrence_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'paused_at', 'next_occurrence_on'], 'recurrence_templates_due_lookup');
        });

        Schema::create('recurrence_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recurrence_template_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_on');
            $table->enum('status', ['generated', 'skipped']);
            $table->foreignId('movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('processed_at')->useCurrent();

            $table->unique(['recurrence_template_id', 'scheduled_on'], 'recurrence_occurrence_unique');
            $table->unique('movement_id');
        });

        Schema::table('movements', function (Blueprint $table): void {
            $table->foreignId('recurrence_template_id')->nullable()->after('original_movement_id')->constrained()->nullOnDelete();
            $table->foreignId('recurrence_occurrence_id')->nullable()->unique()->after('recurrence_template_id')->constrained()->nullOnDelete();
            $table->timestamp('generated_automatically_at')->nullable()->after('recurrence_occurrence_id');
        });

        Schema::create('goal_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movement_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('direction', ['contribution', 'withdrawal']);
            $table->timestamps();

            $table->index(['savings_goal_id', 'direction']);
        });

        Schema::create('recurrence_recovery_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generated_count');
            $table->json('details');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['project_id', 'dismissed_at', 'created_at'], 'recovery_notices_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrence_recovery_notices');
        Schema::dropIfExists('goal_allocations');

        Schema::table('movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recurrence_occurrence_id');
            $table->dropConstrainedForeignId('recurrence_template_id');
            $table->dropColumn('generated_automatically_at');
        });

        Schema::dropIfExists('recurrence_occurrences');
        Schema::dropIfExists('recurrence_templates');
        Schema::dropIfExists('savings_goals');
    }
};
