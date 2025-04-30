<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    //Relacion Un cliente tiene muchos prestamos
    public function prestamos(){
        return $this->hasMany(Prestamo::class);
    }
}
