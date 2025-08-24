<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoParcialAuditoria extends Model
{
    protected $table = 'pagoparcialauditoria'; // nombre exacto

    protected $fillable = [
        'prestamo_id',
        'cliente_id',
        'idusuario',
        'fecha',
        'monto',
        'metodo',
        'descripcion',
    ];

    public function prestamo() { return $this->belongsTo(\App\Models\Prestamo::class); }
    public function cliente()  { return $this->belongsTo(\App\Models\Cliente::class, 'cliente_id'); }
    public function usuario()  { return $this->belongsTo(\App\Models\User::class, 'idusuario'); }
}
