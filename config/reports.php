<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Límite de filas de los reportes
    |--------------------------------------------------------------------------
    |
    | Dompdf consume ~0,45 MB de memoria y ~18 ms por fila (medido con un
    | dataset de 3 000 ventas), así que un reporte sin fechas podría tumbar el
    | proceso. 300 filas ≈ 147 MB y ~2,8 s, con margen sobre `memory_limit`
    | (512 MB); 1 000 filas ≈ 508 MB y el dataset completo agota la memoria.
    | El reporte de coches agrega por SQL, así que su tamaño no depende del
    | número de coches. Un valor de 0 desactiva el límite.
    |
    */

    'max_rows' => (int) env('REPORTS_MAX_ROWS', 300),

];
