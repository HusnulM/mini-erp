<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\Store;
use Modules\Core\Models\User;
use Modules\Core\Services\Organization;
use Modules\Core\Services\PlanLimitExceeded;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesProvisionedTenant;
use Tests\TestCase;

/** PRD §8 data scope (user_scopes + global scope) and plan limits (TDD §8). */
class DataScopeAndLimitsTest extends TestCase
{
    use DatabaseMigrations, UsesProvisionedTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionedTenant(); // STARTER: 1 company, 2 stores, 5 users
    }

    private function userWithScopes(array $scopes, string $username = 'staff'): User
    {
        $role = Role::findOrCreate('STAFF', 'web');
        $role->givePermissionTo(['core.company.view', 'core.store.view', 'core.branch.view']);
        $user = User::create(['name' => ucfirst($username), 'username' => $username, 'email' => "{$username}@alpha.test", 'password' => 'staff-password-1', 'status' => 'active']);
        $user->assignRole($role);
        foreach ($scopes as [$type, $id]) {
            $user->scopes()->create(['scope_type' => $type, 'scope_id' => $id]);
        }

        return $user;
    }

    #[Test]
    public function users_only_see_the_units_in_their_scope(): void
    {
        $this->tenant->run(function () {
            $company = Company::sole();
            $org = app(Organization::class);
            $hq = Branch::where('code', 'HQ')->sole();
            $sby = $org->createBranch(['company_id' => $company->id, 'code' => 'SBY', 'name' => 'Surabaya']);
            $org->createStore(['company_id' => $company->id, 'branch_id' => $sby->id, 'code' => 'S02', 'name' => 'Toko SBY']);

            $branchUser = $this->userWithScopes([['branch', $sby->id]], 'sby');
            $storeUser = $this->userWithScopes([['store', Store::where('code', 'S01')->value('id')]], 'toko1');
            $companyUser = $this->userWithScopes([['company', $company->id]], 'all');
            $noScope = $this->userWithScopes([], 'none');

            auth('web')->setUser($branchUser);
            $this->assertSame(['S02'], Store::pluck('code')->all());
            $this->assertSame(['SBY'], Branch::pluck('code')->all());

            auth('web')->setUser($storeUser);
            $this->assertSame(['S01'], Store::pluck('code')->all());

            auth('web')->setUser($companyUser);
            $this->assertEqualsCanonicalizing(['S01', 'S02'], Store::pluck('code')->all());

            auth('web')->setUser($noScope);
            $this->assertSame([], Store::pluck('code')->all(), 'deny by default');

            auth('web')->setUser(User::where('email', 'ani@alpha.test')->sole());
            $this->assertCount(2, Store::all(), 'SUPER ADMIN sees everything');

            auth('web')->logout();
            $this->assertCount(2, Store::all(), 'jobs and console are not restricted');
            $this->assertNotNull($hq);
        });
    }

    #[Test]
    public function the_own_scope_shows_records_the_user_created(): void
    {
        $this->tenant->run(function () {
            $user = $this->userWithScopes([['own', null]], 'own');
            auth('web')->setUser($user);
            app(Organization::class)->createBranch(['company_id' => Company::sole()->id, 'code' => 'MINE', 'name' => 'Mine']);

            $this->assertSame(['MINE'], Branch::pluck('code')->all());
        });
    }

    #[Test]
    public function plan_limits_are_enforced_when_creating_records(): void
    {
        $this->tenant->run(function () {
            $org = app(Organization::class);
            $branch = Branch::sole();

            $org->createStore(['company_id' => $branch->company_id, 'branch_id' => $branch->id, 'code' => 'S02', 'name' => 'Toko 2']);

            try {
                $org->createStore(['company_id' => $branch->company_id, 'branch_id' => $branch->id, 'code' => 'S03', 'name' => 'Toko 3']);
                $this->fail('STARTER allows 2 stores');
            } catch (PlanLimitExceeded $e) {
                $this->assertSame('Batas paket tercapai: maksimal 2 store. Upgrade paket untuk menambah.', $e->getMessage());
            }

            $this->expectExceptionMessage('maksimal 1 company');
            $org->createCompany(['code' => 'BETA', 'name' => 'Beta', 'base_currency' => 'IDR']);
        });
    }

    #[Test]
    public function the_organization_page_shows_the_limit_message(): void
    {
        $this->loginAsOwner();

        $this->post($this->url('organization/companies'), ['code' => 'BETA', 'name' => 'Beta', 'base_currency' => 'IDR'])
            ->assertSessionHasErrors(['organization' => 'Batas paket tercapai: maksimal 1 company. Upgrade paket untuk menambah.']);
    }
}
