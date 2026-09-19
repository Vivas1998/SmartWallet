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
            $table->dropIndex('movements_project_id_occurred_on_trashed_at_index');
            $table->dropIndex('movements_project_id_type_occurred_on_index');
            $table->dropIndex('movements_project_id_paid_by_user_id_occurred_on_index');

            $table->index(['project_id', 'trashed_at', 'occurred_on', 'id'], 'movements_project_active_date');
            $table->index(['project_id', 'trashed_at', 'type', 'occurred_on', 'id'], 'movements_project_active_type_date');
            $table->index(['project_id', 'trashed_at', 'category_id', 'occurred_on', 'id'], 'movements_project_active_category_date');
            $table->index(['project_id', 'trashed_at', 'paid_by_user_id', 'occurred_on', 'id'], 'movements_project_active_member_date');
            $table->index(['project_id', 'trashed_at', 'financial_account_id', 'occurred_on', 'id'], 'movements_project_active_source_date');
            $table->index(['project_id', 'trashed_at', 'destination_account_id', 'occurred_on', 'id'], 'movements_project_active_destination_date');
            $table->index(['project_id', 'trashed_at', 'id'], 'movements_project_active_id');
            $table->index(['original_movement_id', 'trashed_at', 'amount_cents'], 'movements_original_active_amount');
            $table->index(['project_id', 'purge_at', 'trashed_at', 'id'], 'movements_project_trash_purge');
            $table->index(['purge_at', 'trashed_at', 'id'], 'movements_purge_due');
        });
    }

    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table): void {
            $table->dropIndex('movements_project_active_date');
            $table->dropIndex('movements_project_active_type_date');
            $table->dropIndex('movements_project_active_category_date');
            $table->dropIndex('movements_project_active_member_date');
            $table->dropIndex('movements_project_active_source_date');
            $table->dropIndex('movements_project_active_destination_date');
            $table->dropIndex('movements_project_active_id');
            $table->dropIndex('movements_original_active_amount');
            $table->dropIndex('movements_project_trash_purge');
            $table->dropIndex('movements_purge_due');

            $table->index(['project_id', 'occurred_on', 'trashed_at']);
            $table->index(['project_id', 'type', 'occurred_on']);
            $table->index(['project_id', 'paid_by_user_id', 'occurred_on']);
        });
    }
};
