<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('type', [
                'checking',
                'savings',
                'cash',
                'credit_card',
                'external_investment',
            ]);
            $table->bigInteger('initial_balance_cents')->default(0);
            $table->date('initial_balance_date');
            $table->unsignedBigInteger('credit_limit_cents')->nullable();
            $table->char('color', 7)->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'archived_at', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
