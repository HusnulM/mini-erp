<?php

namespace App\Central\Enums;

enum TenantStatus: string
{
    case Pending = 'pending';           // registered, email not verified / not paid
    case Provisioning = 'provisioning'; // database being created
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';          // invoice overdue, still usable (grace)
    case Suspended = 'suspended';       // blocked by operator
    case Cancelled = 'cancelled';
    case Failed = 'failed';             // provisioning failed permanently

    /** Can users of this tenant log in and use the app? */
    public function canAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }

    /** Is the tenant still being set up (show a "please wait" page)? */
    public function isPreparing(): bool
    {
        return in_array($this, [self::Pending, self::Provisioning, self::Failed], true);
    }
}
