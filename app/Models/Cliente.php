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
<<<<<<< HEAD
        'direccion', // <-- Campo agregado
        'nombre_referencia1', // <-- Campo agregado
        'telefono_referencia1', // <-- Campo agregado
        'nombre_referencia2', // <-- Campo agregado
        'telefono_referencia2', // <-- Campo agregado
=======
        'nombre_referencia1',
        'telefono_referencia1',
        'nombre_referencia2',
        'telefono_referencia2',
>>>>>>> 88f82b23b70cfaeeb8a0002d12a9c1d2ac43ee5c
    ];

    // Relación: Un cliente tiene muchos préstamos
    public function prestamos()
    {
        return $this->hasMany(Prestamo::class);
    }
}
