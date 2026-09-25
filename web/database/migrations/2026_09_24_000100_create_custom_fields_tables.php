<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('name_normalized', 80);
            $table->enum('type', ['text', 'number', 'date', 'boolean']);
            $table->json('applicable_movement_types');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_initial')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'name_normalized']);
            $table->index(['project_id', 'archived_at', 'position'], 'custom_field_definitions_listing');
        });

        Schema::create('custom_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_field_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('planned_movement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('recurrence_template_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('value_text', 255)->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_definition_id', 'movement_id'], 'custom_field_values_movement_unique');
            $table->unique(['custom_field_definition_id', 'planned_movement_id'], 'custom_field_values_plan_unique');
            $table->unique(['custom_field_definition_id', 'recurrence_template_id'], 'custom_field_values_recurrence_unique');
            $table->index(['custom_field_definition_id', 'value_text'], 'custom_field_values_text_lookup');
            $table->index(['custom_field_definition_id', 'value_number'], 'custom_field_values_number_lookup');
            $table->index(['custom_field_definition_id', 'value_date'], 'custom_field_values_date_lookup');
            $table->index(['custom_field_definition_id', 'value_boolean'], 'custom_field_values_boolean_lookup');
        });

        $now = now();
        $examples = [
            ['Número de factura', 'número de factura', 'text'],
            ['Fecha de garantía', 'fecha de garantía', 'date'],
            ['Gasto deducible', 'gasto deducible', 'boolean'],
            ['Método de compra', 'método de compra', 'text'],
            ['Cubierto por el seguro', 'cubierto por el seguro', 'boolean'],
        ];

        foreach (DB::table('projects')->select(['id', 'creator_user_id'])->orderBy('id')->get() as $project) {
            foreach ($examples as $position => [$name, $normalized, $type]) {
                DB::table('custom_field_definitions')->insert([
                    'project_id' => $project->id,
                    'name' => $name,
                    'name_normalized' => $normalized,
                    'type' => $type,
                    'applicable_movement_types' => json_encode(['expense'], JSON_THROW_ON_ERROR),
                    'position' => $position,
                    'is_initial' => true,
                    'created_by_user_id' => $project->creator_user_id,
                    'updated_by_user_id' => $project->creator_user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
