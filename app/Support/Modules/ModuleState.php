<?php

namespace App\Support\Modules;

enum ModuleState: string
{
    case Active = 'active';
    case ReadOnly = 'readonly'; // subscription/license expired: GET only
    case Inactive = 'inactive';

    public function allowsWrite(): bool
    {
        return $this === self::Active;
    }

    public function allowsRead(): bool
    {
        return $this !== self::Inactive;
    }
}
