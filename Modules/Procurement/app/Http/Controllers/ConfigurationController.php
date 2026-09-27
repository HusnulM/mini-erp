<?php

namespace Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentSequence;
use Modules\Core\Models\User;
use Modules\Core\Services\Settings;
use Modules\Procurement\Models\ProcurementType;
use Modules\Procurement\Models\PurchasingGroup;

/**
 * Procurement configuration per company (TDD §9): purchase types,
 * purchasing groups, and a link to the procurement settings. Also the
 * module's setup-wizard step.
 */
class ConfigurationController extends Controller
{
    public function index(Request $request, Settings $settings): View
    {
        $companies = Company::orderBy('code')->get();
        $company = $companies->firstWhere('id', $request->integer('company_id')) ?? $companies->first();

        return view('procurement::configuration', [
            'companies' => $companies,
            'company' => $company,
            'types' => $company ? ProcurementType::where('company_id', $company->id)->orderBy('code')->get() : collect(),
            'groups' => $company ? PurchasingGroup::with('lead')->where('company_id', $company->id)->orderBy('code')->get() : collect(),
            'settings' => $company ? $settings->all('procurement', $company->id) : [],
            'definitions' => $settings->definitions('procurement'),
            'wizard' => tenant()->setup_completed_at === null,
        ]);
    }

    public function editType(ProcurementType $type): View
    {
        return view('procurement::type-form', [
            'type' => $type,
            'sequences' => DocumentSequence::where('company_id', $type->company_id)->where('module', 'procurement')->orderBy('document_type')->get(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $data = $this->validateType($request);
        $type = ProcurementType::create($data);

        return $this->back($type->company_id, "Purchase type {$type->code} dibuat.");
    }

    public function updateType(Request $request, ProcurementType $type): RedirectResponse
    {
        $data = $this->validateType($request, $type);
        $type->update($data);

        return $this->back($type->company_id, "Purchase type {$type->code} diperbarui.");
    }

    public function editGroup(PurchasingGroup $group): View
    {
        return view('procurement::group-form', [
            'group' => $group,
            'users' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $group = PurchasingGroup::create($this->validateGroup($request));

        return $this->back($group->company_id, "Purchasing group {$group->code} dibuat.");
    }

    public function updateGroup(Request $request, PurchasingGroup $group): RedirectResponse
    {
        $group->update($this->validateGroup($request, $group));

        return $this->back($group->company_id, "Purchasing group {$group->code} diperbarui.");
    }

    private function validateType(Request $request, ?ProcurementType $type = null): array
    {
        $companyId = $type?->company_id ?? $request->integer('company_id');

        $data = $request->validate([
            'company_id' => $type ? ['prohibited'] : ['required', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('procurement_types', 'code')->where('company_id', $companyId)->ignore($type)],
            'name' => ['required', 'string', 'max:100'],
            'requires_pr' => ['boolean'],
            'requires_gr' => ['boolean'],
            'pr_sequence_id' => ['nullable', Rule::exists('document_sequences', 'id')->where('company_id', $companyId)],
            'po_sequence_id' => ['nullable', Rule::exists('document_sequences', 'id')->where('company_id', $companyId)],
            'allowed_product_types' => ['array'],
            'allowed_product_types.*' => [Rule::in(['stock', 'non_stock'])],
            'account_mapping_key' => ['nullable', 'string', 'max:40'],
            'is_active' => ['boolean'],
        ]);

        return $data + [
            'requires_pr' => $request->boolean('requires_pr'),
            'requires_gr' => $request->boolean('requires_gr'),
            // Unchecked boxes are not posted: absent means false when editing.
            'is_active' => $type ? $request->boolean('is_active') : $request->boolean('is_active', true),
            'allowed_product_types' => $data['allowed_product_types'] ?? ['stock', 'non_stock'],
        ];
    }

    private function validateGroup(Request $request, ?PurchasingGroup $group = null): array
    {
        $companyId = $group?->company_id ?? $request->integer('company_id');

        $data = $request->validate([
            'company_id' => $group ? ['prohibited'] : ['required', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('purchasing_groups', 'code')->where('company_id', $companyId)->ignore($group)],
            'name' => ['required', 'string', 'max:100'],
            'lead_user_id' => ['nullable', Rule::exists('users', 'id')->where('status', 'active')],
            'max_po_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        return $data + ['is_active' => $group ? $request->boolean('is_active') : $request->boolean('is_active', true)];
    }

    private function back(int $companyId, string $message): RedirectResponse
    {
        return redirect()->route('procurement.setup', ['company_id' => $companyId])->with('status', $message);
    }
}
