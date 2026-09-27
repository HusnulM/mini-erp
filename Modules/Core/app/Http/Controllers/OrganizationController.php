<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Store;
use Modules\Core\Models\Warehouse;
use Modules\Core\Services\Organization;
use Modules\Core\Services\PlanLimitExceeded;

/**
 * Companies, branches, stores and warehouses (setup-wizard step 3 and the
 * "Organisasi" page). Lists are filtered by the user's data scope.
 */
class OrganizationController extends Controller
{
    public function __construct(private readonly Organization $organization) {}

    public function index(Request $request): View
    {
        return view('core::organization.index', [
            'wizard' => $request->boolean('wizard') && tenant()->setup_completed_at === null,
            'companies' => Company::orderBy('code')->get(),
            'branches' => Branch::with('company')->orderBy('code')->get(),
            'stores' => Store::with(['company', 'branch', 'defaultWarehouse'])->orderBy('code')->get(),
            'warehouses' => Warehouse::with(['company', 'branch'])->orderBy('code')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('companies', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'base_currency' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)],
        ]);

        return $this->attempt($request, fn () => $this->organization->createCompany($data + ['timezone' => config('app.timezone') === 'UTC' ? 'Asia/Jakarta' : config('app.timezone')]), 'Company');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('branches', 'code')->where('company_id', $request->input('company_id'))],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->attempt($request, fn () => $this->organization->createBranch($data), 'Branch');
    }

    public function storeStore(Request $request): RedirectResponse
    {
        // The store belongs to the company of its branch.
        $request->merge(['company_id' => Branch::whereKey($request->integer('branch_id'))->value('company_id')]);

        $data = $request->validate([
            'company_id' => ['required', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $request->input('company_id'))],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('stores', 'code')->where('company_id', $request->input('company_id')),
                Rule::unique('warehouses', 'code')->where('company_id', $request->input('company_id'))],
            'name' => ['required', 'string', 'max:150'],
        ], ['code.unique' => 'Kode ini sudah dipakai store atau gudang lain.']);

        return $this->attempt($request, fn () => $this->organization->createStore($data), 'Store');
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $request->input('company_id'))],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('warehouses', 'code')->where('company_id', $request->input('company_id'))],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['main', 'store', 'transit'])],
            'allow_negative_stock' => ['boolean'],
        ]);

        return $this->attempt($request, fn () => $this->organization->createWarehouse($data + ['allow_negative_stock' => false]), 'Gudang');
    }

    private function attempt(Request $request, \Closure $create, string $label): RedirectResponse
    {
        $back = redirect()->route('core.organization.index', $request->boolean('wizard') ? ['wizard' => 1] : []);

        try {
            $model = $create();
        } catch (PlanLimitExceeded $e) {
            return $back->withInput()->withErrors(['organization' => $e->getMessage()]);
        }

        return $back->with('status', "{$label} {$model->code} ditambahkan.");
    }
}
