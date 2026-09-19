<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('name_normalized', 60);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('merged_into_tag_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'name_normalized']);
            $table->index(['project_id', 'archived_at', 'name']);
        });

        Schema::create('movement_tag', function (Blueprint $table): void {
            $table->foreignId('movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['movement_id', 'tag_id']);
            $table->index(['tag_id', 'movement_id']);
        });

        Schema::create('recurrence_template_tag', function (Blueprint $table): void {
            $table->foreignId('recurrence_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['recurrence_template_id', 'tag_id'], 'recurrence_template_tag_primary');
            $table->index(['tag_id', 'recurrence_template_id'], 'recurrence_template_tag_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrence_template_tag');
        Schema::dropIfExists('movement_tag');
        Schema::dropIfExists('tags');
    }
};
