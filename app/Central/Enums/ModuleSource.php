<?php

namespace App\Central\Enums;

/** Why a tenant has a module. */
enum ModuleSource: string
{
    case Core = 'core';
    case Plan = 'plan';
    case Addon = 'addon';
    case License = 'license';
}
