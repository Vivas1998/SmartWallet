<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->enum('type', ['expense', 'income']);
            $table->string('name', 120);
            $table->char('color', 7);
            $table->string('icon', 40);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_initial')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'type', 'parent_id', 'position']);
            $table->index(['project_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
