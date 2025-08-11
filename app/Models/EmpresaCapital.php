<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaCapital extends Model
{
    use HasFactory;

    protected $table = 'empresa_capital'; // Nombre exacto de la tabla

    protected $fillable = [
        'capital_total',
        'capital_disponible',
        'capital_anterior', // ✅ Agregado
    ];
}
