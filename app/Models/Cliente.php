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
        'direccion', // <-- Campo agregado
        'nombre_referencia1', // <-- Campo agregado
        'telefono_referencia1', // <-- Campo agregado
        'nombre_referencia2', // <-- Campo agregado
        'telefono_referencia2', // <-- Campo agregado
    ];

    // Relación: Un cliente tiene muchos préstamos
    public function prestamos()
    {
        return $this->hasMany(Prestamo::class);
    }
}
