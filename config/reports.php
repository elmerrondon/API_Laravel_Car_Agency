<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Límite de filas de los reportes
    |--------------------------------------------------------------------------
    |
    | Dompdf consume ~0,4 MB de memoria y ~20 ms por fila, así que un reporte
    | sin fechas podría tumbar el proceso. Se corta en `max_rows` ventas y se
    | pide acotar el rango. El reporte de coches agrega por SQL, así que su
    | tamaño no depende del número de coches. Un valor de 0 desactiva el límite.
    |
    */

    'max_rows' => (int) env('REPORTS_MAX_ROWS', 500),

];
