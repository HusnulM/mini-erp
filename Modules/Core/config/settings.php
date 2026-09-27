<?php

/*
| Core settings (TDD §9). Read with setting('core.<key>', companyId: ...).
*/

return [
    'is_pkp' => [
        'type' => 'bool',
        'default' => false,
        'label' => 'Pengusaha Kena Pajak (PKP)',
        'help' => 'PKP memungut PPN di penjualan dan mengkreditkan PPN pembelian.',
    ],
    'vat_rate' => [
        'type' => 'decimal',
        'default' => config('erp.default_vat_rate'),
        'label' => 'Tarif PPN (%)',
        'rules' => ['numeric', 'min:0', 'max:100'],
    ],
];
