<?php

use App\Support\CentralRoutes;

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
