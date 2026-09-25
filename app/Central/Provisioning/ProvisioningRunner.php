<?php

namespace App\Central\Provisioning;

use App\Central\Enums\CentralUserRole;
use App\Central\Enums\ProvisioningRunType;
use App\Central\Enums\ProvisioningStatus;
use App\Central\Enums\SubscriptionStatus;
use App\Central\Enums\TenantStatus;
use App\Central\Jobs\ProvisionTenant;
use App\Central\Models\CentralUser;
use App\Central\Models\ProvisioningRun;
use App\Central\Models\ProvisioningStep;
use App\Central\Models\Tenant;
use App\Central\Notifications\ProvisioningFailed;
use App\Central\Notifications\TenantReady;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Runs a `create` provisioning run step by step (TDD §7 "Langkah provisioning").
 *
 * Every step is idempotent and logged in provisioning_steps. A failing step
 * is retried up to erp.provisioning.max_attempts times with the configured
 * backoff; after that the run and the tenant are marked failed and operators
 * are notified. retryFrom() restarts a failed run from a chosen step; steps
 * that are already done are skipped.
 */
class ProvisioningRunner
{
    public const STEPS = [
        'reserve_names',
        'create_database',
        'create_db_user',
        'migrate_core',
        'install_modules',
        'seed_defaults',
        'create_admin',
        'finalize',
    ];

    public function __construct(
        private readonly TenantDatabaseProvisioner $database,
        private readonly ModuleInstaller $modules,
        private readonly TenantAdminCreator $admin,
    ) {}

    public function createRun(Tenant $tenant): ProvisioningRun
    {
        return DB::connection('central')->transaction(function () use ($tenant) {
            $run = ProvisioningRun::create([
                'tenant_id' => $tenant->id,
                'type' => ProvisioningRunType::Create,
                'status' => ProvisioningStatus::Pending,
            ]);

            foreach (self::STEPS as $i => $step) {
                $run->steps()->create(['step' => $step, 'seq' => $i + 1, 'status' => ProvisioningStatus::Pending]);
            }

            return $run;
        });
    }

    public function dispatch(ProvisioningRun $run): void
    {
        ProvisionTenant::dispatch($run->id);
    }

    /**
     * Executes the run's unfinished steps in order.
     *
     * @return int|null seconds to wait before the next attempt when a step
     *                  failed but has attempts left; null when the run is
     *                  done, failed permanently, or owned by another worker
     */
    public function execute(ProvisioningRun $run): ?int
    {
        $lock = Cache::lock("provisioning-run:{$run->id}", 900);

        if (! $lock->get()) {
            return null;
        }

        try {
            return $this->executeSteps($run->fresh());
        } finally {
            $lock->release();
        }
    }

    /**
     * Operator "Retry from step": resets $step and every later step to
     * pending and dispatches the job again. Earlier steps must be done.
     */
    public function retryFrom(ProvisioningRun $run, ProvisioningStep $step): void
    {
        if ((int) $step->run_id !== (int) $run->id) {
            throw new InvalidArgumentException('The step does not belong to this run.');
        }

        DB::connection('central')->transaction(function () use ($run, $step) {
            $run = ProvisioningRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->status !== ProvisioningStatus::Failed) {
                throw new LogicException('Hanya run yang gagal yang bisa di-retry.');
            }

            $unfinished = $run->steps()
                ->whereNotIn('status', [ProvisioningStatus::Done, ProvisioningStatus::Skipped])
                ->min('seq');

            if ($unfinished !== null && $step->seq > $unfinished) {
                throw new LogicException('Langkah sebelumnya belum selesai; retry harus dimulai paling lambat dari langkah yang gagal.');
            }

            $run->steps()->where('seq', '>=', $step->seq)->update([
                'status' => ProvisioningStatus::Pending,
                'attempts' => 0,
                'message' => null,
                'started_at' => null,
                'finished_at' => null,
            ]);

            $run->update(['status' => ProvisioningStatus::Pending, 'error' => null, 'finished_at' => null]);
            $run->tenant->update(['status' => TenantStatus::Provisioning]);
        });

        $this->dispatch($run);
    }

    /** Marks the run failed from outside the step loop (job timeout / worker crash). */
    public function abort(ProvisioningRun $run, string $error): void
    {
        $run = $run->fresh();

        if (in_array($run->status, [ProvisioningStatus::Done, ProvisioningStatus::Failed], true)) {
            return;
        }

        $step = $run->steps()->where('status', ProvisioningStatus::Running)->first();
        $step?->update(['status' => ProvisioningStatus::Failed, 'message' => $error, 'finished_at' => now()]);

        $this->markFailed($run, $step?->step, $error);
    }

    private function executeSteps(ProvisioningRun $run): ?int
    {
        // A permanently failed run only restarts through retryFrom().
        if (in_array($run->status, [ProvisioningStatus::Done, ProvisioningStatus::Failed], true)) {
            return null;
        }

        $tenant = $run->tenant;
        $run->update(['status' => ProvisioningStatus::Running, 'started_at' => $run->started_at ?? now()]);

        if ($tenant->status !== TenantStatus::Provisioning) {
            $tenant->update(['status' => TenantStatus::Provisioning]);
        }

        $maxAttempts = (int) config('erp.provisioning.max_attempts');
        $backoff = config('erp.provisioning.backoff');

        foreach ($run->steps()->get() as $step) {
            if (in_array($step->status, [ProvisioningStatus::Done, ProvisioningStatus::Skipped], true)) {
                continue;
            }

            $step->update([
                'status' => ProvisioningStatus::Running,
                'attempts' => $step->attempts + 1,
                'message' => null,
                'started_at' => now(),
                'finished_at' => null,
            ]);

            try {
                $message = $this->perform($step->step, $tenant->fresh());
                $step->update(['status' => ProvisioningStatus::Done, 'message' => $message, 'finished_at' => now()]);
            } catch (Throwable $e) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                report($e);
                $error = Str::limit($e->getMessage(), 2000);
                $step->update(['status' => ProvisioningStatus::Failed, 'message' => $error, 'finished_at' => now()]);

                if ($step->attempts < $maxAttempts) {
                    $run->update(['error' => "{$step->step}: {$error}"]);

                    return (int) ($backoff[$step->attempts - 1] ?? end($backoff));
                }

                $this->markFailed($run, $step->step, $error);

                return null;
            }
        }

        $run->update(['status' => ProvisioningStatus::Done, 'error' => null, 'finished_at' => now()]);

        return null;
    }

    /** @return string|null short result shown in the operator panel */
    private function perform(string $step, Tenant $tenant): ?string
    {
        switch ($step) {
            case 'reserve_names':
                $this->database->reserveNames($tenant);

                return "{$tenant->db_name} / {$tenant->db_username}";
            case 'create_database':
                $this->database->createDatabase($tenant);

                return null;
            case 'create_db_user':
                $this->database->createDatabaseUser($tenant);

                return null;
            case 'migrate_core':
                $this->database->migrateCoreModules($tenant);

                return null;
            case 'install_modules':
                return implode(', ', $this->modules->install($tenant));
            case 'seed_defaults':
                $this->database->seed($tenant);

                return null;
            case 'create_admin':
                return $this->admin->create($tenant);
            case 'finalize':
                return $this->finalize($tenant);
        }

        throw new InvalidArgumentException("Unknown provisioning step [{$step}].");
    }

    private function finalize(Tenant $tenant): string
    {
        $subscription = $tenant->currentSubscription()->first();
        $status = $subscription?->status === SubscriptionStatus::Active ? TenantStatus::Active : TenantStatus::Trial;

        $tenant->update(['status' => $status]);

        // Send the "ready" email once, even if finalize is retried.
        $registration = $tenant->registration();

        if (empty($registration['ready_notified_at'])) {
            Notification::route('mail', [$tenant->owner_email => $tenant->owner_name])
                ->notify(new TenantReady($tenant));

            $tenant->setRegistration([...$registration, 'ready_notified_at' => now()->toIso8601String()])->save();
        }

        return $status->value;
    }

    private function markFailed(ProvisioningRun $run, ?string $step, string $error): void
    {
        $run->update([
            'status' => ProvisioningStatus::Failed,
            'error' => $step ? "{$step}: {$error}" : $error,
            'finished_at' => now(),
        ]);
        $run->tenant->update(['status' => TenantStatus::Failed]);

        $operators = CentralUser::query()
            ->where('is_active', true)
            ->whereIn('role', [CentralUserRole::Owner, CentralUserRole::Support])
            ->get();

        Notification::send($operators, new ProvisioningFailed($run->fresh('tenant')));
    }
}
