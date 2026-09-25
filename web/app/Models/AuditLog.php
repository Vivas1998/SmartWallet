<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id', 'actor_user_id', 'subject_type', 'subject_id', 'action', 'before_values', 'after_values', 'created_at',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'created' => 'Creación',
            'generated' => 'Generación automática',
            'updated' => 'Modificación',
            'updated_from_original' => 'Ajuste vinculado',
            'trashed' => 'Envío a papelera',
            'restored' => 'Restauración',
            'archived' => 'Archivado',
            'reactivated' => 'Reactivación',
            'merged' => 'Fusión',
            'paused' => 'Pausa',
            'resumed' => 'Reanudación',
            'next_skipped' => 'Próxima aparición omitida',
            'generation_paused' => 'Pausa automática por datos no disponibles',
            'completed' => 'Registro como realizado',
            'cancelled' => 'Cancelación',
            'purged' => 'Eliminación definitiva',
            'member_removed' => 'Retirada de acceso',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            'project' => 'Proyecto',
            'movement' => 'Movimiento',
            'account' => 'Cuenta',
            'budget' => 'Presupuesto',
            'goal' => 'Objetivo',
            'recurrence' => 'Serie recurrente',
            'planned_movement' => 'Planificación',
            'tag' => 'Etiqueta',
            'custom_field' => 'Campo personalizado',
            'member' => 'Miembro',
            default => ucfirst(str_replace('_', ' ', $this->subject_type)),
        };
    }

    public function activityDescription(): string
    {
        $subject = match ($this->subject_type) {
            'project' => 'el proyecto',
            'movement' => 'un movimiento',
            'account' => 'una cuenta',
            'budget' => 'el presupuesto',
            'goal' => 'un objetivo',
            'recurrence' => 'una serie recurrente',
            'planned_movement' => 'una planificación',
            'tag' => 'una etiqueta',
            'custom_field' => 'un campo personalizado',
            'member' => 'un miembro',
            default => 'un elemento',
        };

        $action = match ($this->action) {
            'created' => 'creó',
            'generated' => 'generó automáticamente',
            'updated' => 'modificó',
            'updated_from_original' => 'ajustó desde el original',
            'trashed' => 'envió a la papelera',
            'restored' => 'restauró',
            'archived' => 'archivó',
            'reactivated' => 'reactivó',
            'merged' => 'fusionó',
            'paused' => 'pausó',
            'resumed' => 'reanudó',
            'next_skipped' => 'omitió la próxima aparición de',
            'generation_paused' => 'pausó automáticamente',
            'completed' => 'registró como realizado',
            'cancelled' => 'canceló',
            'purged' => 'eliminó definitivamente',
            'member_removed' => 'retiró',
            default => strtolower($this->actionLabel()),
        };

        return $action.' '.$subject;
    }
}
