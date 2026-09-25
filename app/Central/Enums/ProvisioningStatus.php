<?php

namespace App\Central\Enums;

/** Status of a provisioning run or step. */
enum ProvisioningStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
