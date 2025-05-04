<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    use HasFactory;

    //Relacion un prestamos pertenece a un cliente
    public function cliente(){
        return $this->belongsTo(Cliente::class);
    }

    //Relacion un prestamo tiene muchos pagos
    public function pagos(){
        return $this->hasMany(Pago::class);
    }
}
