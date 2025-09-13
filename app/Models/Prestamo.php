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

    public function cliente(){ return $this->belongsTo(Cliente::class); }
    public function pagos(){ return $this->hasMany(Pago::class, 'prestamo_id'); }
    public function usuario(){ return $this->belongsTo(User::class, 'idusuario'); }
    public function abonos(){ return $this->hasMany(Abono::class, 'prestamo_id'); }

    /**
     * saldo_actual = monto_total - (cuotas confirmadas + abonos de cuotas pendientes)
     * (Evita doble conteo de abonos en cuotas ya confirmadas.)
     */
    public function getSaldoActualAttribute()
    {
        // 1) Total de cuotas confirmadas
        $totalConfirmado = (float) $this->pagos()
            ->where('estado', 'Confirmado')
            ->sum('monto_pagado');

        // 2) Mapa de cuotas (nro => ['estado','monto'])
        $pagos = $this->pagos()->orderBy('fecha_pago')->get(['id','estado','monto_pagado']);
        $porNro = [];
        foreach ($pagos as $i => $p) {
            $porNro[$i+1] = ['estado' => $p->estado, 'monto' => (float)$p->monto_pagado];
        }

        // 3) Abonos agrupados por nro_cuota (de este préstamo)
        $abonosPorCuota = DB::table('abonos')
            ->where('prestamo_id', $this->id)
            ->selectRaw('nro_cuota, SUM(COALESCE(monto, monto_abonado, 0)) as total')
            ->groupBy('nro_cuota')
            ->pluck('total', 'nro_cuota');

        // 4) Sumar SOLO abonos de cuotas PENDIENTES (cap por valor de cuota)
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
