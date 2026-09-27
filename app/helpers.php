<?php

use App\Support\CentralRoutes;
use Modules\Core\Services\Settings;

if (! function_exists('central_route')) {
    /**
     * URL of a central route (name without the "central." prefix) on the
     * current central domain, or on the canonical one outside a central request.
     */
    function central_route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return route(CentralRoutes::name($name), $parameters, $absolute);
    }
}

if (! function_exists('setting')) {
    /**
     * Tenant setting, resolved company → tenant → default (TDD §9):
     * setting('procurement.gr_over_receipt_tolerance_pct', companyId: $po->company_id).
     */
    function setting(string $key, ?int $companyId = null): mixed
    {
        return app(Settings::class)->get($key, null, $companyId);
    }
}
