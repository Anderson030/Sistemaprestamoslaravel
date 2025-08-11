<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroCapital extends Model
{
    protected $fillable = ['monto', 'user_id', 'tipo_accion'];
    protected $table = 'registros_capital';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
