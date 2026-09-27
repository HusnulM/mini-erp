<?php

namespace Modules\Core\Services;

use App\Contracts\ModuleEntitlement;
use App\Support\Modules\Installer;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Models\Store;
use Modules\Core\Models\User;
use Modules\Core\Models\Warehouse;

/**
 * Creates organization units with plan limits (TDD §8, ModuleEntitlement::
 * limits()) and the per-company defaults of every installed module.
 */
class Organization
{
    public function __construct(
        private readonly ModuleEntitlement $entitlement,
        private readonly DocumentNumbers $numbers,
        private readonly ModuleRegistry $registry,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function createCompany(array $data): Company
    {
        $this->assertLimit('companies', Company::count());

        return DB::transaction(function () use ($data) {
            $company = Company::create($data);
            $this->applyCompanyDefaults($company);

            return $company;
        });
    }

    /** Document sequences + each installed module's Installer::seed() (idempotent). */
    public function applyCompanyDefaults(Company $company): void
    {
        $installed = InstalledModule::pluck('module_code')->all();
        $this->numbers->ensureForCompany($company, $installed);

        foreach ($installed as $code) {
            $class = $this->registry->has($code) ? $this->registry->get($code)->installer : null;
            $installer = $class ? app($class) : null;

            if ($installer instanceof Installer) {
                $installer->seed();
            }
        }
    }

    /** @param  array<string, mixed>  $data */
    public function createBranch(array $data): Branch
    {
        return Branch::create($data);
    }

    /**
     * A store always has a default warehouse: the given one, or a new
     * store warehouse with the store's code.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStore(array $data): Store
    {
        $this->assertLimit('stores', Store::withoutGlobalScopes()->count());

        return DB::transaction(function () use ($data) {
            if (empty($data['default_warehouse_id'])) {
                $data['default_warehouse_id'] = Warehouse::create([
                    'company_id' => $data['company_id'],
                    'branch_id' => $data['branch_id'],
                    'code' => $data['code'],
                    'name' => 'Gudang '.$data['name'],
                    'type' => 'store',
                ])->id;
            }

            return Store::create($data);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function createWarehouse(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function assertCanAddUser(): void
    {
        $this->assertLimit('users', User::where('status', 'active')->count());
    }

    private function assertLimit(string $resource, int $current): void
    {
        $limits = $this->entitlement->limits();

        if (! $limits->allows($resource, $current)) {
            throw PlanLimitExceeded::for($resource, $limits->{$resource});
        }
    }
}
