<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->char('color', 7)->default('#147d68');
            $table->string('icon', 40)->default('home');
            $table->char('currency', 3)->default('EUR');
            $table->string('locale', 10)->default('es');
            $table->string('timezone', 64)->default('Europe/Madrid');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['archived_at', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
