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

    /**
     * Relación: Un pago pertenece a un préstamo.
     */
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }
}
