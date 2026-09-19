<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['expense', 'income', 'transfer', 'refund', 'investment_contribution']);
            $table->unsignedBigInteger('amount_cents');
            $table->date('occurred_on');
            $table->string('concept', 180);
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('original_movement_id')->nullable()->constrained('movements')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('purge_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('restored_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'occurred_on', 'trashed_at']);
            $table->index(['project_id', 'type', 'occurred_on']);
            $table->index(['project_id', 'category_id', 'subcategory_id', 'occurred_on', 'amount_cents'], 'movements_duplicate_lookup');
            $table->index(['project_id', 'paid_by_user_id', 'occurred_on']);
        });

        Schema::create('account_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->bigInteger('signed_amount_cents');
            $table->date('occurred_on');
            $table->timestamps();

            $table->index(['project_id', 'financial_account_id', 'occurred_on'], 'account_entries_balance_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_entries');
        Schema::dropIfExists('movements');
    }
};
