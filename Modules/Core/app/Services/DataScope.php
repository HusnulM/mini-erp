<?php

namespace Modules\Core\Services;

use Modules\Core\Models\User;
use Modules\Core\Models\UserScope;

class DataScope
{
    public const TYPES = ['company', 'branch', 'store', 'warehouse', 'own'];

    /**
     * @return array{company: list<int>, branch: list<int>, store: list<int>, warehouse: list<int>, own: ?int}|null
     *                                                                                                              null = unrestricted
     */
    public function forCurrentUser(): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $user = auth('web')->user();

        if (! $user instanceof User || $user->hasRole(config('erp.provisioning.admin_role'))) {
            return null;
        }

        $rows = UserScope::where('user_id', $user->id)->get(['scope_type', 'scope_id']);
        $allowed = ['company' => [], 'branch' => [], 'store' => [], 'warehouse' => [], 'own' => null];

        foreach ($rows as $row) {
            if ($row->scope_type === 'own') {
                $allowed['own'] = $user->id;
            } elseif (isset($allowed[$row->scope_type])) {
                $allowed[$row->scope_type][] = (int) $row->scope_id;
            }
        }

        return $allowed;
    }
}
