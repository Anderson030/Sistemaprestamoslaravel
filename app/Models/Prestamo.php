<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'monto_prestado',
        'tasa_interes',
        'modalidad',
        'nro_cuotas',
        'fecha_inicio',
        'monto_total',
        'idusuario',
        'estado',
    ];

    /**
     * Relación: Un préstamo pertenece a un cliente.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relación: Un préstamo tiene muchos pagos.
     */
    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Relación: Un préstamo pertenece a un usuario (prestamista).
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'idusuario');
    }
}
