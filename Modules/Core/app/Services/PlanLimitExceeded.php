<?php

namespace Modules\Core\Services;

use RuntimeException;

class PlanLimitExceeded extends RuntimeException
{
    public static function for(string $resource, int $limit): self
    {
        $label = ['users' => 'user', 'companies' => 'company', 'stores' => 'store'][$resource] ?? $resource;

        return new self("Batas paket tercapai: maksimal {$limit} {$label}. Upgrade paket untuk menambah.");
    }
}
