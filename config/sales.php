<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fecha mínima de venta
    |--------------------------------------------------------------------------
    |
    | `StoreSaleRequest` rechaza ventas anteriores a esta fecha (Y-m-d) y
    | ventas futuras. Ajustable con SALES_MIN_DATE para admitir históricos
    | más antiguos sin relajar el resto de la validación.
    |
    */
    'min_date' => (string) env('SALES_MIN_DATE', '2000-01-01'),
];
