<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoCapitalPrestamista extends Model
{
    use HasFactory;

    protected $table = 'movimientos_capital_prestamistas';

    protected $fillable = [
        'user_id',
        'monto',
        'descripcion',
        'fecha',
    ];

    protected $dates = ['fecha'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
