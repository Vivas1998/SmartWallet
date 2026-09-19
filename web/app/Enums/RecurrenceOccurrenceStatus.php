<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurrenceOccurrenceStatus: string
{
    case Generated = 'generated';
    case Skipped = 'skipped';
}
