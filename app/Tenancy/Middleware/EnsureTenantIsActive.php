<?php

namespace App\Tenancy\Middleware;

use App\Central\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks tenants that are not usable yet (still provisioning) or any more
 * (suspended / cancelled). Runs right after tenancy is initialized.
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        $status = $tenant?->status;

        if ($status instanceof TenantStatus && $status->canAccess()) {
            return $next($request);
        }

        $preparing = $status instanceof TenantStatus && $status->isPreparing();

        return response()->view('tenancy.unavailable', [
            'tenant' => $tenant,
            'preparing' => $preparing,
        ], $preparing ? 503 : 403);
    }
}
