<?php

namespace App\Support\Modules;

use App\Contracts\ModuleEntitlement;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Route;

/**
 * Tenant menu built from module.json "menu" entries: only modules the tenant
 * can use (active or readonly), only items the user has permission for.
 * Nothing is hard-coded, so activating a module shows its menu at once.
 */
class MenuBuilder
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleEntitlement $entitlement,
    ) {}

    /** @return list<array{label: string, url: string, module: string, readonly: bool}> */
    public function for(?Authorizable $user): array
    {
        $items = [];

        foreach ($this->registry->all() as $code => $manifest) {
            $state = $this->entitlement->state($code);

            if (! $state->allowsRead()) {
                continue;
            }

            foreach ($manifest->menu as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }
                if ($item['permission'] !== null && ! $user?->can($item['permission'])) {
                    continue;
                }

                $items[] = [
                    'label' => $item['label'],
                    'url' => route($item['route'], absolute: false),
                    'module' => $code,
                    'readonly' => $state === ModuleState::ReadOnly,
                    'order' => $item['order'],
                ];
            }
        }

        usort($items, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_map(fn ($i) => array_diff_key($i, ['order' => true]), $items);
    }
}
