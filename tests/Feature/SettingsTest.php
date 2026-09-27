<?php

namespace Tests\Feature;

use App\Central\Modules\ModuleManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Models\Company;
use Modules\Core\Models\User;
use Modules\Core\Services\Settings;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesProvisionedTenant;
use Tests\TestCase;

/** TDD §9 settings: company → tenant → default, typed, validated, cached. */
class SettingsTest extends TestCase
{
    use DatabaseMigrations, UsesProvisionedTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionedTenant('BUSINESS');
    }

    #[Test]
    public function values_resolve_company_then_tenant_then_default(): void
    {
        $this->tenant->run(function () {
            $settings = app(Settings::class);
            $alpha = Company::sole();
            $beta = Company::create(['code' => 'BETA', 'name' => 'Beta', 'base_currency' => 'IDR']); // bypasses the plan limit

            $this->assertSame(30, setting('procurement.default_payment_terms_days', $alpha->id), 'default');

            $settings->set('procurement', ['default_payment_terms_days' => 45]);
            $this->assertSame(45, setting('procurement.default_payment_terms_days', $alpha->id), 'tenant level');

            $settings->set('procurement', ['default_payment_terms_days' => 14, 'po_requires_approved_pr' => false], $beta->id);
            $this->assertSame(14, setting('procurement.default_payment_terms_days', $beta->id), 'company level wins');
            $this->assertFalse(setting('procurement.po_requires_approved_pr', $beta->id));
            $this->assertSame(45, setting('procurement.default_payment_terms_days', $alpha->id), 'other company unchanged');
            $this->assertTrue(setting('procurement.po_requires_approved_pr', $alpha->id));
        });
    }

    #[Test]
    public function values_are_validated_against_their_definition(): void
    {
        $this->tenant->run(function () {
            try {
                app(Settings::class)->set('procurement', ['gr_over_receipt_tolerance_pct' => 150]);
                $this->fail('150% should be refused');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('gr_over_receipt_tolerance_pct', $e->errors());
            }

            $this->expectException(InvalidArgumentException::class);
            app(Settings::class)->set('procurement', ['no_such_key' => 1]);
        });
    }

    #[Test]
    public function the_settings_page_is_built_from_the_definitions_and_saves_per_company(): void
    {
        $this->loginAsOwner();
        $company = $this->tenant->run(fn () => Company::sole());

        $this->get($this->url("settings/procurement?company_id={$company->id}"))
            ->assertOk()
            ->assertSee('Toleransi penerimaan barang melebihi PO (%)')
            ->assertSee('Termin pembayaran default (hari)')
            ->assertSee('Core');

        $this->put($this->url('settings/procurement'), [
            'company_id' => $company->id,
            'gr_over_receipt_tolerance_pct' => '2.5',
            'invoice_price_tolerance_pct' => '1',
            'default_payment_terms_days' => '60',
            // po_requires_approved_pr unchecked → false
            'allow_po_without_vendor_price' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant->run(function () use ($company) {
            $this->assertSame(2.5, setting('procurement.gr_over_receipt_tolerance_pct', $company->id));
            $this->assertFalse(setting('procurement.po_requires_approved_pr', $company->id));
            $this->assertSame(60, setting('procurement.default_payment_terms_days', $company->id));
        });
    }

    #[Test]
    public function settings_of_inactive_modules_and_without_permission_are_refused(): void
    {
        $this->tenant->run(function () {
            $role = Role::create(['name' => 'STAFF', 'guard_name' => 'web']);
            $role->givePermissionTo('core.company.view');
            User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@alpha.test', 'password' => 'staff-password-1', 'status' => 'active'])->assignRole($role);
        });
        $this->post($this->url('login'), ['login' => 'staff', 'password' => 'staff-password-1']);
        $this->get($this->url('settings/procurement'))->assertForbidden();
        $this->post($this->url('logout'));

        $this->loginAsOwner();
        $this->get($this->url('settings/procurement'))->assertOk();
        app(ModuleManager::class)->deactivate($this->tenant, 'procurement');
        $this->get($this->url('settings/procurement'))->assertNotFound();
        $this->get($this->url('settings/nope'))->assertNotFound();
    }
}
