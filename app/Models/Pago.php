<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    //Relacion un pago pertenece a un prestamo
    public function prestamo(){
        return $this->belongsTo(Prestamo::class);
    }
}
