<?php

use App\Models\Configuracion;

if (!function_exists('formatear_pesos')) {
    function formatear_pesos($valor)
    {
        $configuracion = Configuracion::first(); // suponiendo que tienes solo 1 configuración
        if ($configuracion && $configuracion->moneda == 'cop') {
            return '$' . number_format($valor, 0, ',', '.'); // Peso colombiano con puntos
        }
        
        // Aquí puedes extender para otras monedas si quieres (usd, eur, etc)
        return '$' . number_format($valor, 2, '.', ','); // Formato estándar
    }
}
