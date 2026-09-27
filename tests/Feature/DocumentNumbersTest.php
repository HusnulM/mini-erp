<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentSequence;
use Modules\Core\Services\DocumentNumbers;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\UsesProvisionedTenant;
use Tests\TestCase;

/** TDD §5 document_sequences and acceptance criterion "tidak pernah dobel pada 100 request paralel". */
class DocumentNumbersTest extends TestCase
{
    use DatabaseMigrations, UsesProvisionedTenant;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionedTenant();
        $this->company = $this->tenant->run(fn () => Company::sole());
    }

    private function numbers(): DocumentNumbers
    {
        return app(DocumentNumbers::class);
    }

    #[Test]
    public function numbers_follow_the_default_format_and_reset_yearly(): void
    {
        $this->tenant->run(function () {
            $d = CarbonImmutable::parse('2026-12-31');
            $this->assertSame('ADJ-2026-000001', $this->numbers()->next($this->company->id, 'ADJ', date: $d));
            $this->assertSame('ADJ-2026-000002', $this->numbers()->next($this->company->id, 'ADJ', date: $d));
            $this->assertSame('ADJ-2027-000001', $this->numbers()->next($this->company->id, 'ADJ', date: $d->addDay()));
        });
    }

    #[Test]
    public function custom_formats_monthly_reset_and_branch_sequences(): void
    {
        $this->tenant->run(function () {
            $branch = Branch::sole();
            DocumentSequence::where('document_type', 'TRF')->update(['prefix' => 'TR', 'format' => '{PREFIX}/{YY}{MM}/{SEQ:4}', 'reset_period' => 'monthly']);
            DocumentSequence::create([
                'company_id' => $this->company->id, 'branch_id' => $branch->id, 'module' => 'inventory',
                'document_type' => 'TRF', 'prefix' => 'TRB', 'format' => '{PREFIX}-{BRANCH}-{SEQ}', 'reset_period' => 'never',
            ]);

            $sep = CarbonImmutable::parse('2026-09-15');
            $this->assertSame('TR/2609/0001', $this->numbers()->next($this->company->id, 'TRF', date: $sep));
            $this->assertSame('TR/2610/0001', $this->numbers()->next($this->company->id, 'TRF', date: $sep->addMonth()));
            $this->assertSame('TRB-HQ-1', $this->numbers()->next($this->company->id, 'TRF', $branch->id, $sep));
            $this->assertSame('TRB-HQ-2', $this->numbers()->next($this->company->id, 'TRF', $branch->id, $sep->addYear()));
        });
    }

    #[Test]
    public function an_unknown_document_type_fails_clearly(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No document sequence for [XYZ]');

        $this->tenant->run(fn () => $this->numbers()->next($this->company->id, 'XYZ'));
    }

    #[Test]
    public function a_rolled_back_document_gives_its_number_back(): void
    {
        $this->tenant->run(function () {
            try {
                DB::transaction(function () {
                    $this->numbers()->next($this->company->id, 'ADJ');
                    throw new RuntimeException('document failed');
                });
            } catch (RuntimeException) {
            }

            $this->assertStringEndsWith('000001', $this->numbers()->next($this->company->id, 'ADJ'));
        });
    }

    #[Test]
    public function taking_a_number_is_not_audited_but_changing_the_format_is(): void
    {
        $this->tenant->run(function () {
            $before = AuditLog::count();
            $this->numbers()->next($this->company->id, 'ADJ');
            $this->assertSame($before, AuditLog::count());

            DocumentSequence::where('document_type', 'ADJ')->first()->update(['prefix' => 'AJ']);
            $this->assertSame(['prefix' => 'AJ'], AuditLog::latest('id')->first()->new_values);
        });
    }

    /**
     * 100 numbers taken by 20 processes at the same time, each with its
     * own MySQL connection: no duplicates, no gaps.
     */
    #[Test]
    #[RequiresPhpExtension('pcntl')]
    public function one_hundred_parallel_requests_never_get_the_same_number(): void
    {
        $processes = 20;
        $perProcess = 5;
        $dir = sys_get_temp_dir().'/erp-numbers-'.uniqid();
        mkdir($dir);
        $tenant = $this->tenant;
        $companyId = $this->company->id;

        // Children must not reuse the parent's sockets.
        DB::disconnect('central');
        DB::disconnect('tenant');
        DB::disconnect('provisioner');

        $pids = [];
        for ($p = 0; $p < $processes; $p++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $numbers = [];
                try {
                    $tenant->run(function () use (&$numbers, $companyId, $perProcess) {
                        for ($i = 0; $i < $perProcess; $i++) {
                            $numbers[] = app(DocumentNumbers::class)->next($companyId, 'ADJ');
                        }
                    });
                } catch (\Throwable $e) {
                    $numbers[] = 'ERROR: '.$e->getMessage();
                }
                file_put_contents("{$dir}/{$p}.json", json_encode($numbers));
                posix_kill(getmypid(), SIGKILL); // exit without running shutdown handlers
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $all = [];
        foreach (glob("{$dir}/*.json") as $file) {
            $all = [...$all, ...json_decode(file_get_contents($file), true)];
            unlink($file);
        }
        rmdir($dir);

        $this->assertCount($processes * $perProcess, $all, 'every process reported');
        $this->assertEmpty(array_filter($all, fn ($n) => str_starts_with($n, 'ERROR')), implode("\n", $all));
        $this->assertCount($processes * $perProcess, array_unique($all), 'no duplicate numbers');

        $seq = array_map(fn ($n) => (int) substr($n, -6), $all);
        sort($seq);
        $this->assertSame(range(1, $processes * $perProcess), $seq, 'no gaps');
    }
}
