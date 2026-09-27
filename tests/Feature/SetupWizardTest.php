<?php

namespace Tests\Feature;

use App\Central\Modules\ModuleManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentSequence;
use Modules\Core\Models\FiscalYear;
use Modules\Core\Models\SetupProgress;
use Modules\Core\Models\Store;
use Modules\Core\Models\TaxCode;
use Modules\Core\Models\User;
use Modules\Core\Models\Warehouse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesProvisionedTenant;
use Tests\TestCase;

/** TDD §9 setup wizard and acceptance criterion "wizard wajib selesai ... bisa dilanjutkan setelah logout". */
class SetupWizardTest extends TestCase
{
    use DatabaseMigrations, UsesProvisionedTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionedTenant(setup: false);
        $this->loginAsOwner();
    }

    private function doCompanyStep(): void
    {
        $this->post($this->url('setup/company'), [
            'code' => 'ALPHA', 'name' => 'Toko Alpha', 'legal_name' => 'PT Toko Alpha',
            'tax_id' => '01.234.567.8-901.000', 'address' => 'Jl. Merdeka 1', 'base_currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
        ])->assertSessionHasNoErrors()->assertRedirect(route('core.setup.fiscal-year'));
    }

    #[Test]
    public function every_page_redirects_to_the_wizard_until_setup_is_done(): void
    {
        $this->get($this->url('/'))->assertRedirect(route('core.setup.index'));
        $this->get($this->url('inventory'))->assertRedirect(route('core.setup.index'));
        $this->get($this->url('audit-logs'))->assertRedirect(route('core.setup.index'));
        $this->getJson($this->url('inventory'))->assertStatus(409);

        $this->get($this->url('setup'))->assertOk()
            ->assertSeeInOrder(['Perusahaan', 'Tahun fiskal', 'Struktur organisasi', 'Pajak', 'Penomoran dokumen', 'User &amp; role'], false)
            ->assertDontSee('Chart of Accounts');
    }

    #[Test]
    public function the_required_steps_complete_the_setup(): void
    {
        Storage::fake('local');

        $this->post($this->url('setup/finish'))->assertRedirect(route('core.setup.index'))->assertSessionHasErrors('setup');

        // 1. Company (with logo)
        $this->post($this->url('setup/company'), [
            'code' => 'ALPHA', 'name' => 'Toko Alpha', 'tax_id' => '0123456789012345', 'base_currency' => 'IDR',
            'timezone' => 'Asia/Jakarta', 'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('core.setup.fiscal-year'));

        // 2. Fiscal year starting in April → 12 periods to March next year
        $this->post($this->url('setup/fiscal-year'), ['start_month' => 4, 'year' => 2026])
            ->assertRedirect(route('core.setup.organization'));

        // 3. Organization: continuing without a store or warehouse is refused
        $this->post($this->url('setup/organization'))->assertSessionHasErrors('organization');
        $company = $this->tenant->run(fn () => Company::sole());
        $this->post($this->url('organization/branches?wizard=1'), ['company_id' => $company->id, 'code' => 'HQ', 'name' => 'Pusat'])->assertSessionHasNoErrors();
        $branchId = $this->tenant->run(fn () => Branch::sole()->id);
        $this->post($this->url('organization/stores?wizard=1'), ['branch_id' => $branchId, 'code' => 'S01', 'name' => 'Toko 1'])->assertSessionHasNoErrors();
        $this->post($this->url('setup/organization'))->assertRedirect(route('core.setup.tax'));

        // 4. Tax: PKP 11%
        $this->post($this->url('setup/tax'), ['is_pkp' => 1, 'vat_rate' => 11])->assertRedirect(route('core.setup.numbering'));

        $this->post($this->url('setup/finish'))->assertRedirect(route('core.dashboard'));
        $this->assertNotNull($this->tenant->fresh()->setup_completed_at);
        $this->get($this->url('/'))->assertOk()->assertSee('Toko Alpha');

        $this->tenant->run(function () use ($company) {
            $this->assertNotNull($company->fresh()->logo_path);
            $this->assertSame('0123456789012345', $company->fresh()->tax_id);
            $this->assertSame($company->id, User::sole()->default_company_id);

            $fy = FiscalYear::with('periods')->sole();
            $this->assertSame('2026-04-01', $fy->start_date->toDateString());
            $this->assertSame('2027-03-31', $fy->end_date->toDateString());
            $this->assertCount(12, $fy->periods);
            $this->assertSame('2027-03-31', $fy->periods->last()->end_date->toDateString());

            $store = Store::sole();
            $this->assertSame('store', Warehouse::findOrFail($store->default_warehouse_id)->type, 'a store gets its own warehouse');

            $this->assertEqualsCanonicalizing(['PPN-IN', 'PPN-OUT'], TaxCode::pluck('code')->all());
            $this->assertEquals(11, TaxCode::where('code', 'PPN-OUT')->value('rate'));
            $this->assertTrue(setting('core.is_pkp', $company->id));

            // Sequences for the document types of the installed modules.
            $this->assertEqualsCanonicalizing(['ADJ', 'TRF', 'OPN', 'POS', 'RET'], DocumentSequence::pluck('document_type')->all());
        });
    }

    #[Test]
    public function progress_survives_logging_out(): void
    {
        $this->doCompanyStep();
        $this->post($this->url('logout'));

        $this->loginAsOwner();
        $this->get($this->url('/'))->assertRedirect(route('core.setup.index'));
        $this->get($this->url('setup'))->assertOk()->assertSee('Lanjut: Tahun fiskal');
        $this->tenant->run(fn () => $this->assertSame('done', SetupProgress::where('step', 'company')->value('status')));
    }

    #[Test]
    public function required_steps_cannot_be_skipped_but_optional_ones_can(): void
    {
        $this->post($this->url('setup/steps/company/skip'))->assertSessionHasErrors('setup');
        $this->post($this->url('setup/steps/numbering/skip'))->assertSessionHasNoErrors();

        $this->tenant->run(fn () => $this->assertSame('skipped', SetupProgress::where('step', 'numbering')->value('status')));
    }

    #[Test]
    public function steps_that_need_a_company_send_back_to_the_company_step(): void
    {
        $this->get($this->url('setup/fiscal-year'))->assertRedirect(route('core.setup.company'));
        $this->post($this->url('setup/tax'), ['is_pkp' => 0])->assertRedirect(route('core.setup.company'));
    }

    #[Test]
    public function the_company_step_validates_its_input(): void
    {
        $this->post($this->url('setup/company'), ['code' => 'bad code', 'name' => '', 'tax_id' => '123', 'base_currency' => 'XXX', 'timezone' => 'Mars/Base'])
            ->assertSessionHasErrors(['code', 'name', 'tax_id', 'base_currency', 'timezone']);
    }

    #[Test]
    public function the_chart_of_accounts_step_is_required_when_finance_is_active(): void
    {
        app(ModuleManager::class)->addAddon($this->tenant, 'finance');

        $this->get($this->url('setup'))->assertSee('Chart of Accounts');
        $this->post($this->url('setup/steps/coa/skip'))->assertSessionHasErrors('setup');
        $this->post($this->url('setup/coa'), ['template' => 'retail'])->assertSessionHasNoErrors();
    }

    #[Test]
    public function users_without_settings_permission_are_told_to_wait(): void
    {
        $this->tenant->run(function () {
            $role = Role::create(['name' => 'KASIR', 'guard_name' => 'web']);
            $user = User::create(['name' => 'Kasir', 'username' => 'kasir', 'email' => 'kasir@alpha.test', 'password' => 'kasir-password-1', 'status' => 'active']);
            $user->assignRole($role);
        });
        $this->post($this->url('logout'));
        $this->post($this->url('login'), ['login' => 'kasir', 'password' => 'kasir-password-1']);

        $this->get($this->url('/'))->assertForbidden()->assertSee('belum menyelesaikan setup awal');
        $this->get($this->url('setup'))->assertForbidden();
    }
}
