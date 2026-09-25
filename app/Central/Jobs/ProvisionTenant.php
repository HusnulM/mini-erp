<?php

namespace App\Central\Jobs;

use App\Central\Models\ProvisioningRun;
use App\Central\Provisioning\ProvisioningRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Creates a tenant's database and system (TDD §7), on the "provisioning"
 * queue. The steps and their retries live in ProvisioningRunner: when a
 * step fails with attempts left, the job is released with that step's
 * backoff (10s, 60s, 300s) and resumes from the failed step.
 */
class ProvisionTenant implements ShouldQueue
{
    use Queueable;

    /** Upper bound only: the per-step limit is enforced by the runner. */
    public int $tries;

    public int $timeout = 900;

    public function __construct(public int $runId)
    {
        $this->onQueue(config('erp.provisioning.queue'));
        $this->tries = count(ProvisioningRunner::STEPS) * (int) config('erp.provisioning.max_attempts') + 1;
    }

    public function handle(ProvisioningRunner $runner): void
    {
        $run = ProvisioningRun::find($this->runId);

        if (! $run) {
            return;
        }

        $delay = $runner->execute($run);

        if ($delay !== null) {
            $this->release($delay);
        }
    }

    public function failed(?Throwable $e): void
    {
        if ($run = ProvisioningRun::find($this->runId)) {
            app(ProvisioningRunner::class)->abort($run, $e?->getMessage() ?? 'Job failed.');
        }
    }
}
