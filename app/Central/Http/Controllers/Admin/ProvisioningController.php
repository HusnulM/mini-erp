<?php

namespace App\Central\Http\Controllers\Admin;

use App\Central\Models\ProvisioningRun;
use App\Central\Models\ProvisioningStep;
use App\Central\Models\Tenant;
use App\Central\Provisioning\ProvisioningRunner;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use LogicException;

class ProvisioningController extends Controller
{
    /** "Retry from step" (TDD §7): steps already done before $step are skipped. */
    public function retry(Tenant $tenant, ProvisioningRun $run, ProvisioningStep $step, ProvisioningRunner $runner): RedirectResponse
    {
        abort_unless($run->tenant_id === $tenant->id && (int) $step->run_id === (int) $run->id, 404);

        try {
            $runner->retryFrom($run, $step);
        } catch (LogicException $e) {
            return redirect()->to(central_route('admin.tenants.show', $tenant))->withErrors(['retry' => $e->getMessage()]);
        }

        return redirect()->to(central_route('admin.tenants.show', $tenant))
            ->with('status', "Provisioning diulang dari langkah {$step->step}.");
    }
}
