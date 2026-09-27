<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Core\Models\Company;
use Modules\Core\Models\Currency;
use Modules\Core\Models\DocumentSequence;
use Modules\Core\Models\Store;
use Modules\Core\Models\TaxCode;
use Modules\Core\Models\Warehouse;
use Modules\Core\Services\FiscalCalendar;
use Modules\Core\Services\Organization;
use Modules\Core\Services\PlanLimitExceeded;
use Modules\Core\Services\Settings;
use Modules\Core\Setup\SetupWizard;
use RuntimeException;

/** Setup wizard pages (TDD §9). Every save marks its step in setup_progress. */
class SetupController extends Controller
{
    public function __construct(private readonly SetupWizard $wizard) {}

    public function index(): View
    {
        return view('core::setup.index', [
            'steps' => $this->wizard->steps(),
            'missing' => $this->wizard->missing(),
            'completed' => $this->wizard->isCompleted(),
        ]);
    }

    public function company(): View
    {
        return $this->step('company', 'core::setup.company', [
            'company' => $this->currentCompany(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function saveCompany(Request $request, Organization $organization): RedirectResponse
    {
        $company = $this->currentCompany();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('companies', 'code')->ignore($company)],
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'tax_id' => ['nullable', 'string', 'max:30', 'regex:/^[0-9.\-]{15,22}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'base_currency' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)],
            'timezone' => ['required', 'timezone:all'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ], ['tax_id.regex' => 'NPWP harus 15 atau 16 digit (boleh dengan titik/strip).']);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'local');
        }
        unset($data['logo']);

        try {
            $company = $company
                ? tap($company)->update($data)
                : $organization->createCompany($data);
        } catch (PlanLimitExceeded $e) {
            return back()->withInput()->withErrors(['code' => $e->getMessage()]);
        }

        $request->user()->update(['default_company_id' => $request->user()->default_company_id ?? $company->id]);
        $this->wizard->markDone('company', ['company_id' => $company->id]);

        return $this->next();
    }

    public function fiscalYear(): View|RedirectResponse
    {
        return $this->needsCompany() ?? $this->step('fiscal_year', 'core::setup.fiscal-year', [
            'company' => $this->currentCompany()->load('fiscalYears.periods'),
        ]);
    }

    public function saveFiscalYear(Request $request, FiscalCalendar $calendar): RedirectResponse
    {
        if ($redirect = $this->needsCompany()) {
            return $redirect;
        }

        $data = $request->validate([
            'start_month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $year = $calendar->generate($this->currentCompany(), (int) $data['year'], (int) $data['start_month']);
        $this->wizard->markDone('fiscal_year', ['fiscal_year_id' => $year->id]);

        return $this->next();
    }

    public function organization(): View|RedirectResponse
    {
        return $this->needsCompany() ?? redirect()->route('core.organization.index', ['wizard' => 1]);
    }

    /** Step 3 is done with at least one store or one warehouse. */
    public function saveOrganization(): RedirectResponse
    {
        if (! Store::exists() && ! Warehouse::exists()) {
            return redirect()->route('core.organization.index', ['wizard' => 1])
                ->withErrors(['organization' => 'Tambahkan minimal 1 store atau 1 gudang.']);
        }

        $this->wizard->markDone('organization');

        return $this->next();
    }

    public function tax(Settings $settings): View|RedirectResponse
    {
        if ($redirect = $this->needsCompany()) {
            return $redirect;
        }

        $company = $this->currentCompany();

        return $this->step('tax', 'core::setup.tax', [
            'company' => $company,
            'isPkp' => $settings->get('core', 'is_pkp', $company->id),
            'vatRate' => $settings->get('core', 'vat_rate', $company->id),
            'taxCodes' => TaxCode::where('company_id', $company->id)->orderBy('code')->get(),
        ]);
    }

    public function saveTax(Request $request, Settings $settings): RedirectResponse
    {
        if ($redirect = $this->needsCompany()) {
            return $redirect;
        }

        $company = $this->currentCompany();
        $data = $request->validate([
            'is_pkp' => ['required', 'boolean'],
            'vat_rate' => ['required_if:is_pkp,1', 'nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($data, $company, $settings) {
            $isPkp = (bool) $data['is_pkp'];
            $rate = $isPkp ? (float) $data['vat_rate'] : (float) config('erp.default_vat_rate');
            $settings->set('core', ['is_pkp' => $isPkp, 'vat_rate' => $rate], $company->id);

            if ($isPkp) {
                foreach (['PPN-IN' => ['PPN Masukan', 'input'], 'PPN-OUT' => ['PPN Keluaran', 'output']] as $code => [$name, $type]) {
                    $tax = TaxCode::firstOrNew(['company_id' => $company->id, 'code' => $code, 'effective_to' => null]);
                    $tax->fill(['name' => "{$name} {$rate}%", 'rate' => $rate, 'type' => $type, 'effective_from' => $tax->effective_from ?? today()])->save();
                }
            }
        });

        $this->wizard->markDone('tax', ['is_pkp' => (bool) $data['is_pkp']]);

        return $this->next();
    }

    public function coa(): View
    {
        return $this->step('coa', 'core::setup.coa', ['templates' => self::COA_TEMPLATES]);
    }

    /** Chart of accounts is created by the Finance module (Phase 1) from this choice. */
    public function saveCoa(Request $request): RedirectResponse
    {
        $data = $request->validate(['template' => ['required', Rule::in(array_keys(self::COA_TEMPLATES))]]);
        $this->wizard->markDone('coa', $data);

        return $this->next();
    }

    public function numbering(): View|RedirectResponse
    {
        return $this->needsCompany() ?? $this->step('numbering', 'core::setup.numbering', [
            'sequences' => DocumentSequence::where('company_id', $this->currentCompany()->id)->orderBy('module')->orderBy('document_type')->get(),
        ]);
    }

    public function saveNumbering(Request $request): RedirectResponse
    {
        $company = $this->currentCompany();
        $data = $request->validate([
            'sequences' => ['array'],
            'sequences.*.prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\/_-]+$/'],
            'sequences.*.format' => ['required', 'string', 'max:60', 'regex:/\{SEQ(:\d+)?\}/'],
            'sequences.*.reset_period' => ['required', Rule::in(['never', 'yearly', 'monthly'])],
        ], ['sequences.*.format.regex' => 'Format harus memuat {SEQ} atau {SEQ:n}.']);

        foreach ($data['sequences'] ?? [] as $id => $values) {
            DocumentSequence::where('company_id', $company?->id)->findOrFail($id)->update($values);
        }

        $this->wizard->markDone('numbering');

        return $this->next();
    }

    public function users(): View
    {
        return $this->step('users', 'core::setup.users', []);
    }

    /** For optional steps whose work happens on other pages (users, module steps). */
    public function markDone(string $step): RedirectResponse
    {
        abort_unless(collect($this->wizard->steps())->contains('key', $step), 404);
        $this->wizard->markDone($step);

        return $this->next();
    }

    public function skip(string $step): RedirectResponse
    {
        try {
            $this->wizard->skip($step);
        } catch (RuntimeException $e) {
            return back()->withErrors(['setup' => $e->getMessage()]);
        }

        return $this->next();
    }

    public function finish(): RedirectResponse
    {
        try {
            $this->wizard->complete();
        } catch (RuntimeException $e) {
            return redirect()->route('core.setup.index')->withErrors(['setup' => $e->getMessage()]);
        }

        return redirect()->route('core.dashboard')->with('status', 'Setup selesai. Selamat bekerja!');
    }

    public const COA_TEMPLATES = [
        'retail' => 'Retail',
        'distribution' => 'Distribusi',
        'empty' => 'Kosong (import sendiri)',
    ];

    private function step(string $key, string $view, array $data): View
    {
        $steps = $this->wizard->steps();

        return view($view, $data + ['steps' => $steps, 'current' => $key]);
    }

    /** Next step that is not done yet, or the overview. */
    private function next(): RedirectResponse
    {
        $next = collect($this->wizard->steps())->first(fn ($s) => $s['status'] === 'pending');

        return $next && ! $this->wizard->isCompleted()
            ? redirect()->route($next['route'])
            : redirect()->route('core.setup.index');
    }

    private function currentCompany(): ?Company
    {
        $id = auth('web')->user()?->default_company_id;

        return ($id ? Company::find($id) : null) ?? Company::orderBy('id')->first();
    }

    private function needsCompany(): ?RedirectResponse
    {
        return $this->currentCompany() ? null : redirect()->route('core.setup.company')
            ->withErrors(['setup' => 'Isi data perusahaan terlebih dahulu.']);
    }
}
