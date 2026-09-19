<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $request->validate([
            'member' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $actions = ['created', 'generated', 'updated', 'updated_from_original', 'trashed', 'restored', 'archived', 'reactivated', 'merged', 'paused', 'resumed', 'next_skipped', 'generation_paused', 'purged', 'member_removed'];
        $types = ['project', 'movement', 'account', 'budget', 'goal', 'recurrence', 'tag', 'member'];

        $logs = $project->auditLogs()->with('actor')
            ->when($request->filled('member'), fn ($query) => $query->where('actor_user_id', $request->integer('member')))
            ->when(in_array($request->query('action'), $actions, true), fn ($query) => $query->where('action', $request->query('action')))
            ->when(in_array($request->query('type'), $types, true), fn ($query) => $query->where('subject_type', $request->query('type')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', CarbonImmutable::parse($request->query('from'), 'Europe/Madrid')->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', CarbonImmutable::parse($request->query('to'), 'Europe/Madrid')->endOfDay()))
            ->latest('created_at')->latest('id')->paginate(40)->withQueryString();

        return view('audit-logs.index', [
            'project' => $project,
            'logs' => $logs,
            'members' => $project->memberships()->with('user')->get()->pluck('user')->unique('id')->sortBy('name'),
            'actions' => $actions,
            'types' => $types,
        ]);
    }
}
