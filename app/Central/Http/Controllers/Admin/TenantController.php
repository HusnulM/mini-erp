<?php

namespace App\Central\Http\Controllers\Admin;

use App\Central\Enums\TenantStatus;
use App\Central\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TenantStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $tenants = Tenant::query()
            ->with(['domains', 'currentSubscription.plan', 'latestProvisioningRun'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('owner_email', 'like', $like));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('central.admin.tenants.index', [
            'tenants' => $tenants,
            'filters' => $filters,
            'counts' => DB::connection('central')->table('tenants')
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load([
            'domains',
            'currentSubscription.plan',
            'tenantModules.module',
            'provisioningRuns' => fn ($q) => $q->latest('id')->with('steps'),
        ]);

        return view('central.admin.tenants.show', ['tenant' => $tenant]);
    }
}
