<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['owner', 'member']);
            $table->foreignId('added_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'removed_at']);
            $table->index(['project_id', 'role', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};
