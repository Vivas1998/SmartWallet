<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RecurrenceRecoveryNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecurrenceRecoveryNoticeController extends Controller
{
    public function dismiss(Request $request, Project $project, RecurrenceRecoveryNotice $notice): RedirectResponse
    {
        $this->authorize('recordMovements', $project);
        abort_unless($notice->project_id === $project->id, 404);
        $notice->update(['dismissed_at' => now(), 'dismissed_by_user_id' => $request->user()->id]);

        return back()->with('status', 'Aviso de movimientos recuperados revisado.');
    }
}
