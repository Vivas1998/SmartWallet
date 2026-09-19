<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurrenceOccurrenceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['recurrence_template_id', 'scheduled_on', 'status', 'movement_id', 'processed_at'])]
class RecurrenceOccurrence extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'status' => RecurrenceOccurrenceStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecurrenceTemplate::class, 'recurrence_template_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }
}
