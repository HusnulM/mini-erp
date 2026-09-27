<?php

namespace Modules\Core\Services;

use App\Support\Modules\ModuleRegistry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentSequence;
use Modules\Core\Models\InstalledModule;
use RuntimeException;

/**
 * Document numbering (TDD §5 document_sequences). next() locks the sequence
 * row with SELECT … FOR UPDATE, so parallel requests never get the same
 * number. Call it inside the transaction that saves the document, so a
 * rolled-back document also gives its number back.
 *
 * Format tokens: {PREFIX} {YYYY} {YY} {MM} {DD} {BRANCH} {SEQ:n}
 */
class DocumentNumbers
{
    public const DEFAULT_FORMAT = '{PREFIX}-{YYYY}-{SEQ:6}';

    public function __construct(private readonly ModuleRegistry $registry) {}

    public function next(int $companyId, string $documentType, ?int $branchId = null, ?CarbonInterface $date = null): string
    {
        $date ??= now();

        return DB::transaction(function () use ($companyId, $documentType, $branchId, $date) {
            // A branch-specific sequence wins over the company-wide one.
            $sequence = DocumentSequence::where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->where(fn ($q) => $q->whereNull('branch_id')->when($branchId, fn ($q) => $q->orWhere('branch_id', $branchId)))
                ->orderByDesc('branch_key')
                ->lockForUpdate()
                ->first()
                ?? throw new RuntimeException("No document sequence for [{$documentType}] in company {$companyId}.");

            $period = match ($sequence->reset_period) {
                'yearly' => $date->format('Y'),
                'monthly' => $date->format('Y-m'),
                default => null,
            };

            if ($sequence->current_period !== $period) {
                $sequence->current_period = $period;
                $sequence->next_number = 1;
            }

            $number = $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->saveQuietly();

            return $this->format($sequence, $number, $date, $branchId);
        }, attempts: 3);
    }

    public function format(DocumentSequence $sequence, int $number, CarbonInterface $date, ?int $branchId = null): string
    {
        $branch = str_contains($sequence->format, '{BRANCH}') && $branchId
            ? Branch::withoutGlobalScopes()->whereKey($branchId)->value('code')
            : '';

        return preg_replace_callback('/\{SEQ:(\d+)\}/', fn ($m) => str_pad((string) $number, (int) $m[1], '0', STR_PAD_LEFT), strtr($sequence->format, [
            '{PREFIX}' => $sequence->prefix,
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
            '{BRANCH}' => (string) $branch,
            '{SEQ}' => (string) $number,
        ]));
    }

    /** Company-wide sequences for every document type of the installed modules (idempotent). */
    public function ensureForCompany(Company $company, ?array $modules = null): void
    {
        $modules ??= InstalledModule::pluck('module_code')->all();

        foreach ($modules as $code) {
            if (! $this->registry->has($code)) {
                continue;
            }

            foreach ($this->registry->get($code)->documentTypes as $type) {
                DocumentSequence::firstOrCreate(
                    ['company_id' => $company->id, 'branch_id' => null, 'document_type' => $type],
                    ['module' => $code, 'prefix' => $type, 'format' => self::DEFAULT_FORMAT, 'reset_period' => 'yearly'],
                );
            }
        }
    }
}
