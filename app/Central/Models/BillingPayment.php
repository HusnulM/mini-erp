<?php

namespace App\Central\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPayment extends CentralModel
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'payload' => 'array', 'paid_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }
}
