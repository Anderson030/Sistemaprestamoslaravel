<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    // Permitir asignación masiva de estos campos
    protected $fillable = [
        'nro_documento',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'genero',
        'email',
        'celular',
        'ref_celular',
        'nombre_referencia1',
        'telefono_referencia1',
        'nombre_referencia2',
        'telefono_referencia2',
    ];

    // Relación: Un cliente tiene muchos préstamos
    public function prestamos()
    {
        return $this->hasMany(Prestamo::class);
    }
}
