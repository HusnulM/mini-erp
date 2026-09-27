<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Models\AuditLog;

/** Read-only audit trail (PRD §49): there is no way to edit it from the UI. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:30'],
        ]);

        return view('core::audit-logs.index', [
            'logs' => AuditLog::with('user')
                ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('auditable_type', 'like', '%'.addcslashes($t, '%_\\').'%'))
                ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }
}
