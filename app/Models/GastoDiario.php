<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GastoDiario extends Model
{
    use HasFactory;

    protected $table = 'gastos_diarios';

    protected $fillable = [
        'fecha',
        'monto',
        'descripcion',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    // Relación con usuario (opcional)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
