<?php

namespace App\Central\Enums;

enum TenantMode: string
{
    case Saas = 'saas';
    case Onprem = 'onprem';
}
