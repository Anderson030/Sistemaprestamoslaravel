<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Abono extends Model
{
    use HasFactory;

    protected $table = 'abonos';

    protected $fillable = [
        'prestamo_id',
        'nro_cuota',
        'monto',
        'monto_abonado',   // compatibilidad
        'referencia',
        'fecha_pago',      // <- ¡clave para auditorías!
        'estado',          // si tu tabla lo tiene
        'user_id',
    ];

    protected $casts = [
        'prestamo_id'   => 'integer',
        'nro_cuota'     => 'integer',
        'monto'         => 'float',
        'monto_abonado' => 'float',
        'fecha_pago'    => 'date',   // tu columna es DATE
    ];

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }
}
