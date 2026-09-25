<?php

namespace App\Central\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for all central (landlord) models: always uses the central
 * connection, even while tenancy is initialized.
 */
abstract class CentralModel extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];
}
