<?php

namespace Tests\Feature;

use App\Central\Modules\ModuleManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentSequence;
use Modules\Core\Models\Lookup;
use Modules\Core\Services\Organization;
use Modules\Procurement\Models\ProcurementType;
use Modules\Procurement\Models\PurchasingGroup;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesProvisionedTenant;
use Tests\TestCase;

/**
 * Acceptance criterion (TDD §12): the admin can create purchase types and
 * purchasing groups, and change procurement settings per company.
 */
class ProcurementConfigurationTest extends TestCase
{
    use DatabaseMigrations, UsesProvisionedTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionedTenant('BUSINESS'); // includes procurement
        $this->loginAsOwner();
    }

    #[Test]
    public function defaults_are_seeded_for_each_company(): void
    {
        $this->tenant->run(function () {
            $company = Company::sole();
            $reg = ProcurementType::where('company_id', $company->id)->where('code', 'REG')->sole();
            $this->assertSame('Regular', $reg->name);
            $this->assertSame(DocumentSequence::where('document_type', 'PR')->where('company_id', $company->id)->value('id'), $reg->pr_sequence_id);
            $this->assertSame(DocumentSequence::where('document_type', 'PO')->where('company_id', $company->id)->value('id'), $reg->po_sequence_id);
            $this->assertCount(3, Lookup::options('procurement', 'pr_reason'));

            // A company created later gets them too (bypassing the plan limit here).
            $beta = Company::create(['code' => 'BETA', 'name' => 'Beta', 'base_currency' => 'IDR']);
            app(Organization::class)->applyCompanyDefaults($beta);
            $this->assertTrue(ProcurementType::where('company_id', $beta->id)->where('code', 'REG')->exists());
            $this->assertTrue(DocumentSequence::where('company_id', $beta->id)->where('document_type', 'GR')->exists());
        });
    }

    #[Test]
    public function the_admin_creates_purchase_types_and_purchasing_groups(): void
    {
        $company = $this->tenant->run(fn () => Company::sole());

        $this->get($this->url('procurement/configuration'))->assertOk()->assertSee('REG')->assertSee('Regular');

        $this->post($this->url('procurement/configuration/types'), [
            'company_id' => $company->id, 'code' => 'SRV', 'name' => 'Service', 'requires_pr' => '1', 'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Purchase type SRV dibuat.');

        $this->post($this->url('procurement/configuration/groups'), [
            'company_id' => $company->id, 'code' => 'PG01', 'name' => 'Pembelian Makanan', 'max_po_amount' => '50000000', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->post($this->url('procurement/configuration/types'), ['company_id' => $company->id, 'code' => 'SRV', 'name' => 'Dup'])
            ->assertSessionHasErrors('code');

        $this->tenant->run(function () {
            $srv = ProcurementType::where('code', 'SRV')->sole();
            $this->assertTrue($srv->requires_pr);
            $this->assertFalse($srv->requires_gr, 'service has no goods receipt');

            $group = PurchasingGroup::where('code', 'PG01')->sole();
            $this->assertEquals(50000000, $group->max_po_amount);
            $this->assertTrue(AuditLog::where('auditable_type', $group->getMorphClass())->where('event', 'created')->exists());
        });

        // Deactivate via the edit form (unchecked box).
        $srvId = $this->tenant->run(fn () => ProcurementType::where('code', 'SRV')->value('id'));
        $this->get($this->url("procurement/configuration/types/{$srvId}/edit"))->assertOk();
        $this->put($this->url("procurement/configuration/types/{$srvId}"), ['code' => 'SRV', 'name' => 'Service'])->assertSessionHasNoErrors();
        $this->tenant->run(fn () => $this->assertFalse(ProcurementType::find($srvId)->is_active));
    }

    #[Test]
    public function procurement_settings_change_per_company(): void
    {
        $company = $this->tenant->run(fn () => Company::sole());

        $this->put($this->url('settings/procurement'), [
            'company_id' => $company->id, 'gr_over_receipt_tolerance_pct' => 5, 'invoice_price_tolerance_pct' => 2,
            'po_requires_approved_pr' => '1', 'default_payment_terms_days' => 45,
        ])->assertSessionHasNoErrors();

        $this->get($this->url("procurement/configuration?company_id={$company->id}"))->assertOk()->assertSee('45');
        $this->tenant->run(fn () => $this->assertSame(5.0, setting('procurement.gr_over_receipt_tolerance_pct', $company->id)));
    }

    #[Test]
    public function the_module_adds_an_optional_setup_wizard_step(): void
    {
        $this->tenant->update(['setup_completed_at' => null]);
        tenancy()->end(); // the next request resolves the tenant again, as in production

        $this->get($this->url('setup'))->assertOk()->assertSee('Konfigurasi Procurement');
        $this->get($this->url('procurement/configuration'))->assertOk()->assertSee('Langkah opsional setup awal');
        $this->post($this->url('setup/steps/module:procurement/done'))->assertRedirect();
    }

    #[Test]
    public function without_the_module_there_is_no_configuration(): void
    {
        app(ModuleManager::class)->deactivate($this->tenant, 'procurement');

        $this->get($this->url('procurement/configuration'))->assertForbidden();
    }
}
