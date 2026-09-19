<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_leftover_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movement_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('budget_month');
            $table->timestamps();

            $table->index(['project_id', 'budget_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_leftover_allocations');
    }
};
