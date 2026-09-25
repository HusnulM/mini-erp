<?php

namespace App\Tenancy;

use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

/**
 * Tenant ids are lowercase ULIDs: sortable, unguessable, and short enough to
 * build MySQL identifiers from (usernames are limited to 32 characters).
 */
class UlidGenerator implements UniqueIdentifierGenerator
{
    public static function generate($resource): string
    {
        return strtolower((string) Str::ulid());
    }
}
