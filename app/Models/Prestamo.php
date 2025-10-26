<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Prestamo extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id','monto_prestado','tasa_interes','modalidad',
        'nro_cuotas','fecha_inicio','monto_total','idusuario','estado',
    ];

    protected $casts = [
        'monto_prestado' => 'decimal:2',
        'monto_total'    => 'decimal:2',
        'tasa_interes'   => 'decimal:2',
    ];

    public function cliente(){ return $this->belongsTo(Cliente::class); }
    public function pagos(){ return $this->hasMany(Pago::class, 'prestamo_id'); }
    public function usuario(){ return $this->belongsTo(User::class, 'idusuario'); }
    public function abonos(){ return $this->hasMany(Abono::class, 'prestamo_id'); }

    // ✅ Defensivo: si quedaron registros viejos *100, los normaliza al leer
    public function getMontoTotalAttribute($value)
    {
        if ($value === null) return null;
        return ($value >= 10000000) ? $value / 100 : $value;
    }

    /**
     * saldo_actual = monto_total - (cuotas confirmadas + abonos de cuotas pendientes)
     */
    public function getSaldoActualAttribute()
    {
        $totalConfirmado = (float) $this->pagos()
            ->where('estado', 'Confirmado')
            ->sum('monto_pagado');

        $pagos = $this->pagos()->orderBy('fecha_pago')->get(['id','estado','monto_pagado']);
        $porNro = [];
        foreach ($pagos as $i => $p) {
            $porNro[$i+1] = ['estado' => $p->estado, 'monto' => (float)$p->monto_pagado];
        }

        $abonosPorCuota = DB::table('abonos')
            ->where('prestamo_id', $this->id)
            ->selectRaw('nro_cuota, SUM(COALESCE(monto, monto_abonado, 0)) as total')
            ->groupBy('nro_cuota')
            ->pluck('total', 'nro_cuota');

        $abonosPendientes = 0.0;
        foreach ($abonosPorCuota as $nro => $total) {
            $estado = $porNro[$nro]['estado'] ?? 'Pendiente';
            $montoCuota = (float)($porNro[$nro]['monto'] ?? 0);
            if ($estado !== 'Confirmado') {
                $abonosPendientes += min((float)$total, $montoCuota);
            }
        }

        return max(0, (float)$this->monto_total - ($totalConfirmado + $abonosPendientes));
    }
}
