<?php

namespace App\Support\Modules;

/**
 * Optional per-module hook, named in module.json ("installer": FQCN).
 * Runs inside the tenant context after the module's migrations.
 */
interface Installer
{
    /** Default data (e.g. purchase type "Regular", PR/PO sequences). Must be idempotent. */
    public function seed(): void;

    /**
     * Optional setup-wizard steps of this module (TDD §9 "8+ Konfigurasi
     * modul"); `route` is a tenant route name that renders the step.
     *
     * @return list<array{key: string, label: string, route: string}>
     */
    public function wizardSteps(): array;
}
