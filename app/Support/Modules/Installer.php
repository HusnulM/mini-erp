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
     * Setup-wizard steps this module adds (TDD §9, used in Sprint 4).
     *
     * @return list<array{key: string, label: string}>
     */
    public function wizardSteps(): array;
}
