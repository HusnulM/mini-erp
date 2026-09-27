<?php

namespace Modules\Core\Http\Controllers;

use App\Contracts\ModuleEntitlement;
use App\Http\Controllers\Controller;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Models\Company;
use Modules\Core\Services\Settings;

/**
 * "Pengaturan" page built from each active module's config/settings.php
 * (TDD §9), per company or tenant-wide, guarded by {module}.settings.manage.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ModuleRegistry $registry,
        private readonly ModuleEntitlement $entitlement,
    ) {}

    public function edit(Request $request, string $module): View
    {
        $this->authorizeModule($request, $module);
        $companies = Company::orderBy('code')->get();
        $companyId = $request->integer('company_id') ?: $companies->first()?->id;

        return view('core::settings.edit', [
            'module' => $this->registry->get($module),
            'modules' => $this->modulesWithSettings($request),
            'definitions' => $this->settings->definitions($module),
            'values' => $this->settings->all($module, $companyId),
            'companies' => $companies,
            'companyId' => $companyId,
            'readonly' => ! $this->entitlement->state($module)->allowsWrite(),
        ]);
    }

    public function update(Request $request, string $module): RedirectResponse
    {
        $this->authorizeModule($request, $module);
        abort_unless($this->entitlement->state($module)->allowsWrite(), 403, 'Modul dalam mode baca saja.');

        $companyId = $request->integer('company_id') ?: null;
        abort_if($companyId && ! Company::whereKey($companyId)->exists(), 404);

        $definitions = $this->settings->definitions($module);
        $values = [];
        foreach ($definitions as $key => $def) {
            // Unchecked checkboxes are not posted.
            $values[$key] = $def['type'] === 'bool' ? $request->boolean($key) : $request->input($key);
        }

        $this->settings->set($module, $values, $companyId);

        return redirect()->route('core.settings.edit', ['module' => $module, 'company_id' => $companyId])
            ->with('status', 'Pengaturan disimpan.');
    }

    private function authorizeModule(Request $request, string $module): void
    {
        abort_unless($this->registry->has($module) && $this->entitlement->state($module)->allowsRead(), 404);
        abort_unless($this->settings->definitions($module) !== [], 404);
        abort_unless($request->user()->can("{$module}.settings.manage"), 403);
    }

    /** @return array<string, string> code => name, modules the user may configure */
    private function modulesWithSettings(Request $request): array
    {
        $out = [];

        foreach ($this->registry->all() as $code => $manifest) {
            if ($this->entitlement->state($code)->allowsRead()
                && $this->settings->definitions($code) !== []
                && $request->user()->can("{$code}.settings.manage")) {
                $out[$code] = $manifest->name;
            }
        }

        return $out;
    }
}
