<?php

namespace App\Central\Enums;

enum ProvisioningRunType: string
{
    case Create = 'create';
    case InstallModule = 'install_module';
    case Upgrade = 'upgrade';
    case Delete = 'delete';
}
