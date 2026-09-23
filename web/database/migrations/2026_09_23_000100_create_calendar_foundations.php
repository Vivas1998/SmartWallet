<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table): void {
            $table->boolean('show_in_calendar')->default(false)->after('generated_automatically_at');
            $table->index(
                ['project_id', 'show_in_calendar', 'occurred_on', 'trashed_at'],
                'movements_calendar_lookup',
            );
        });

        Schema::create('planned_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['expense', 'income', 'transfer', 'investment_contribution']);
            $table->unsignedBigInteger('amount_cents');
            $table->date('due_on');
            $table->string('concept', 180);
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('savings_goal_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('movement_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'due_on', 'status'], 'planned_movements_calendar_lookup');
            $table->index(['project_id', 'status', 'type'], 'planned_movements_summary_lookup');
        });

        Schema::create('planned_movement_tag', function (Blueprint $table): void {
            $table->foreignId('planned_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['planned_movement_id', 'tag_id'], 'planned_movement_tag_primary');
            $table->index(['tag_id', 'planned_movement_id'], 'planned_movement_tag_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_movement_tag');
        Schema::dropIfExists('planned_movements');

        Schema::table('movements', function (Blueprint $table): void {
            $table->dropIndex('movements_calendar_lookup');
            $table->dropColumn('show_in_calendar');
        });
    }
};
