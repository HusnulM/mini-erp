<?php

namespace Modules\Procurement\Setup;

use App\Support\Modules\Installer;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentSequence;
use Modules\Core\Models\Lookup;
use Modules\Procurement\Models\ProcurementType;

/**
 * Default procurement data (TDD §6 Installer::seed()): purchase type REG
 * "Regular" per company, wired to its PR/PO sequences, and PR reason
 * lookups. Idempotent; runs on install and whenever a company is created.
 */
class ProcurementInstaller implements Installer
{
    public function seed(): void
    {
        foreach (Company::all() as $company) {
            $sequences = DocumentSequence::where('company_id', $company->id)->whereNull('branch_id')
                ->pluck('id', 'document_type');

            ProcurementType::firstOrCreate(['company_id' => $company->id, 'code' => 'REG'], [
                'name' => 'Regular',
                'requires_pr' => true,
                'requires_gr' => true,
                'pr_sequence_id' => $sequences['PR'] ?? null,
                'po_sequence_id' => $sequences['PO'] ?? null,
                'allowed_product_types' => ['stock', 'non_stock'],
            ]);
        }

        foreach (['STOCK_LOW' => 'Stok menipis', 'CUSTOMER_ORDER' => 'Pesanan pelanggan', 'OPERATIONAL' => 'Kebutuhan operasional'] as $code => $label) {
            Lookup::firstOrCreate(
                ['module' => 'procurement', 'group' => 'pr_reason', 'code' => $code],
                ['label' => $label, 'is_system' => $code === 'OPERATIONAL'],
            );
        }
    }

    public function wizardSteps(): array
    {
        return [
            ['key' => 'procurement', 'label' => 'Konfigurasi Procurement', 'route' => 'procurement.setup'],
        ];
    }
}
