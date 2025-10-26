<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $fillable = [
        'prestamo_id',
        'monto_pagado',
        'fecha_pago',
        'metodo_pago',
        'referencia_pago',
        'estado',
        'fecha_cancelado',
    ];

    protected $casts = [
        'monto_pagado' => 'decimal:2',
    ];

    // ✅ Defensivo: normaliza si quedó guardado *100
    public function getMontoPagadoAttribute($value)
    {
        if ($value === null) return null;
        return ($value >= 10000000) ? $value / 100 : $value;
    }

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }
}
