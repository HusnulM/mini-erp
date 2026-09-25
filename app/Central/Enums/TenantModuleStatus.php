<?php

namespace App\Central\Enums;

/** Per-tenant module lifecycle (TDD §6). There is no 'uninstalled': data stays. */
enum TenantModuleStatus: string
{
    case Installing = 'installing';
    case Active = 'active';
    case Readonly = 'readonly';
    case Inactive = 'inactive';
    case Failed = 'failed';
}
