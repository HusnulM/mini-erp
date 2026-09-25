<?php

namespace App\Central\Http\Controllers\Admin;

use App\Central\Models\Tenant;
use App\Central\Modules\ModuleActivationException;
use App\Central\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\RedirectResponse;

/** Module actions on the operator panel's tenant page. {module} is a module code. */
class TenantModuleController extends Controller
{
    public function __construct(private readonly ModuleManager $modules) {}

    public function activate(Tenant $tenant, string $module): RedirectResponse
    {
        return $this->attempt($tenant, fn () => $this->modules->activate($tenant, $module)
            ? "Modul {$module} sedang diinstal."
            : "Modul {$module} sudah aktif.");
    }

    public function deactivate(Tenant $tenant, string $module): RedirectResponse
    {
        return $this->attempt($tenant, function () use ($tenant, $module) {
            $this->modules->deactivate($tenant, $module);

            return "Modul {$module} dinonaktifkan. Datanya tetap tersimpan.";
        });
    }

    public function addon(Tenant $tenant, string $module): RedirectResponse
    {
        return $this->attempt($tenant, function () use ($tenant, $module) {
            $added = $this->modules->addAddon($tenant, $module);

            return 'Add-on ditambahkan: '.(implode(', ', $added) ?: '—').". Modul {$module} sedang diinstal.";
        });
    }

    /** @param  Closure(): string  $action */
    private function attempt(Tenant $tenant, Closure $action): RedirectResponse
    {
        $back = redirect()->to(central_route('admin.tenants.show', $tenant));

        try {
            return $back->with('status', $action());
        } catch (ModuleActivationException $e) {
            return $back->withErrors(['module' => $e->getMessage()]);
        }
    }
}
