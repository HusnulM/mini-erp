<?php

namespace App\Central\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingInvoiceLine extends CentralModel
{
    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'unit_price' => 'decimal:4', 'amount' => 'decimal:4'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }
}
