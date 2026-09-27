<?php

namespace Modules\Core\Setup;

use App\Contracts\ModuleEntitlement;
use App\Support\Modules\Installer;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Modules\Core\Models\SetupProgress;
use RuntimeException;

/**
 * Setup wizard (TDD §9 "Langkah wizard"). Progress is stored per step in
 * setup_progress, so the wizard can be continued after logging out. The
 * tenant's setup_completed_at is set once every required step is done.
 */
class SetupWizard
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleEntitlement $entitlement,
    ) {}

    /**
     * @return list<array{key: string, label: string, required: bool, route: string, status: string}>
     */
    public function steps(): array
    {
        $steps = [
            ['key' => 'company', 'label' => 'Perusahaan', 'required' => true, 'route' => 'core.setup.company'],
            ['key' => 'fiscal_year', 'label' => 'Tahun fiskal', 'required' => true, 'route' => 'core.setup.fiscal-year'],
            ['key' => 'organization', 'label' => 'Struktur organisasi', 'required' => true, 'route' => 'core.setup.organization'],
            ['key' => 'tax', 'label' => 'Pajak', 'required' => true, 'route' => 'core.setup.tax'],
        ];

        if ($this->entitlement->state('finance')->allowsRead()) {
            $steps[] = ['key' => 'coa', 'label' => 'Chart of Accounts', 'required' => true, 'route' => 'core.setup.coa'];
        }

        $steps[] = ['key' => 'numbering', 'label' => 'Penomoran dokumen', 'required' => false, 'route' => 'core.setup.numbering'];
        $steps[] = ['key' => 'users', 'label' => 'User & role', 'required' => false, 'route' => 'core.setup.users'];

        // 8+: one step per active module that declares one.
        foreach ($this->registry->all() as $code => $manifest) {
            if (! $manifest->installer || ! $this->entitlement->state($code)->allowsWrite()) {
                continue;
            }

            $installer = app($manifest->installer);

            foreach ($installer instanceof Installer ? $installer->wizardSteps() : [] as $step) {
                if (Route::has($step['route'])) {
                    $steps[] = ['key' => "module:{$step['key']}", 'label' => $step['label'], 'required' => false, 'route' => $step['route']];
                }
            }
        }

        $status = SetupProgress::pluck('status', 'step');

        return array_map(fn ($s) => $s + ['status' => $status[$s['key']] ?? 'pending'], $steps);
    }

    public function isDone(string $step): bool
    {
        return in_array(SetupProgress::where('step', $step)->value('status'), ['done', 'skipped'], true);
    }

    /** @param  array<string, mixed>  $data */
    public function markDone(string $step, array $data = [], string $status = 'done'): void
    {
        SetupProgress::updateOrCreate(['step' => $step], ['status' => $status, 'completed_at' => now(), 'data' => $data ?: null]);
    }

    public function skip(string $step): void
    {
        $definition = collect($this->steps())->firstWhere('key', $step)
            ?? throw new RuntimeException("Unknown setup step [{$step}].");

        if ($definition['required']) {
            throw new RuntimeException('Langkah wajib tidak bisa dilewati.');
        }

        $this->markDone($step, status: 'skipped');
    }

    /** @return list<string> labels of required steps that are not done */
    public function missing(): array
    {
        return array_values(array_map(
            fn ($s) => $s['label'],
            array_filter($this->steps(), fn ($s) => $s['required'] && ! in_array($s['status'], ['done', 'skipped'], true))
        ));
    }

    public function complete(): void
    {
        if ($missing = $this->missing()) {
            throw new RuntimeException('Langkah wajib belum selesai: '.implode(', ', $missing).'.');
        }

        tenant()->update(['setup_completed_at' => now()]);
    }

    public function isCompleted(): bool
    {
        return tenant()?->setup_completed_at !== null;
    }
}
