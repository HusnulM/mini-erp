<?php

namespace App\Central\Enums;

/** SaaS operator roles. */
enum CentralUserRole: string
{
    case Owner = 'owner';
    case Support = 'support';
    case Billing = 'billing';
}
