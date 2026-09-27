<?php

namespace Modules\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Company;
use Modules\Core\Models\FiscalYear;

class FiscalCalendar
{
    /**
     * Creates fiscal year $year (named after the calendar year it starts in)
     * with 12 monthly periods, starting in $startMonth. Idempotent.
     */
    public function generate(Company $company, int $year, int $startMonth): FiscalYear
    {
        return DB::transaction(function () use ($company, $year, $startMonth) {
            $start = CarbonImmutable::create($year, $startMonth, 1);

            $fiscalYear = FiscalYear::firstOrCreate(
                ['company_id' => $company->id, 'year' => $year],
                ['start_date' => $start, 'end_date' => $start->addYear()->subDay(), 'status' => 'open'],
            );

            for ($i = 0; $i < 12; $i++) {
                $periodStart = $fiscalYear->start_date->toImmutable()->addMonthsNoOverflow($i);
                $fiscalYear->periods()->firstOrCreate(
                    ['period' => $i + 1],
                    ['start_date' => $periodStart, 'end_date' => $periodStart->endOfMonth(), 'status' => 'open'],
                );
            }

            if ($company->fiscal_year_start_month !== $startMonth) {
                $company->update(['fiscal_year_start_month' => $startMonth]);
            }

            return $fiscalYear;
        });
    }
}
