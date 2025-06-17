<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapitalPrestamista extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'monto_asignado',
        'monto_disponible',
    ];

    // Relación: Un capital pertenece a un usuario (prestamista)
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
