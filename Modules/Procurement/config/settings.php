<?php

/*
| Procurement settings (TDD §9 "Contoh: konfigurasi Procurement"), per company.
*/

return [
    'gr_over_receipt_tolerance_pct' => [
        'type' => 'decimal',
        'default' => 0,
        'label' => 'Toleransi penerimaan barang melebihi PO (%)',
        'rules' => ['numeric', 'min:0', 'max:100'],
    ],
    'invoice_price_tolerance_pct' => [
        'type' => 'decimal',
        'default' => 0,
        'label' => 'Toleransi selisih harga invoice (%)',
        'rules' => ['numeric', 'min:0', 'max:100'],
    ],
    'po_requires_approved_pr' => [
        'type' => 'bool',
        'default' => true,
        'label' => 'PO wajib dari PR yang sudah disetujui',
    ],
    'allow_po_without_vendor_price' => [
        'type' => 'bool',
        'default' => true,
        'label' => 'Izinkan PO tanpa harga vendor',
    ],
    'default_payment_terms_days' => [
        'type' => 'int',
        'default' => 30,
        'label' => 'Termin pembayaran default (hari)',
        'rules' => ['integer', 'min:0', 'max:365'],
    ],
];
