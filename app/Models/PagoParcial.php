<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoParcial extends Model
{
    protected $table = 'pagos_parciales';

    protected $fillable = ['cliente_id', 'idusuario', 'nota'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idusuario');
    }
}
