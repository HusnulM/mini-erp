<?php

namespace Tests\Concerns;

use App\Central\Models\Tenant;
use Modules\Core\Models\Company;
use Modules\Core\Models\User;
use Modules\Core\Services\FiscalCalendar;
use Modules\Core\Services\Organization;
use Modules\Core\Setup\SetupWizard;

/**
 * Runs the required setup-wizard steps through the services (not the UI),
 * for tests that need a tenant ready to use: company ALPHA, branch HQ,
 * store S01 (with its warehouse), fiscal year, non-PKP.
 */
trait CompletesSetup
{
    protected function completeSetup(Tenant $tenant): Company
    {
        return $tenant->run(function () use ($tenant) {
            $org = app(Organization::class);
            $company = Company::where('code', 'ALPHA')->first()
                ?? $org->createCompany(['code' => 'ALPHA', 'name' => $tenant->name, 'base_currency' => 'IDR']);
            $branch = $org->createBranch(['company_id' => $company->id, 'code' => 'HQ', 'name' => 'Kantor Pusat']);
            $org->createStore(['company_id' => $company->id, 'branch_id' => $branch->id, 'code' => 'S01', 'name' => 'Toko 1']);
            app(FiscalCalendar::class)->generate($company, (int) now()->year, 1);
            User::query()->update(['default_company_id' => $company->id]);

            $wizard = app(SetupWizard::class);
            foreach ($wizard->steps() as $step) {
                if ($step['required']) {
                    $wizard->markDone($step['key']);
                }
            }
            $wizard->complete();

            return $company;
        });
    }
}
