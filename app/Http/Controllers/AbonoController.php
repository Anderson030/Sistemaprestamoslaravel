<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\EmpresaCapital;
use App\Models\RegistroCapital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AbonoController extends Controller
{
    /**
     * Registra un abono (pago parcial) a una cuota específica de un préstamo.
     * Robusto para que NUNCA marque como completo si el monto es menor a la cuota fija.
     */
    public function store(Request $request, $prestamoId)
    {
        // 0) Validación de entrada
        $request->validate([
            'nro_cuota'  => 'required|integer|min:1',
            // Permitimos "150.000", "150,000" o "150000"
            'monto'      => ['required', 'string', 'max:50'],
            'referencia' => 'nullable|string|max:255',
            'fecha_pago' => 'nullable|date',
        ]);

        // Helpers para trabajar "en centavos" (en tu caso pesos enteros normalizados)
        $toCents = function ($val) {
            if ($val === null) return 0;
            // Quita todo lo que no sea dígito: "150.000" -> "150000", "150,000.50" -> "15000050"
            $clean = preg_replace('/[^\d]/', '', (string)$val);
            return (int) $clean;
        };
        // Si manejas decimales reales, cambia a dividir entre 100.
        $toPesos = fn (int $cents) => (float) $cents;

        // 1) Cargar préstamo
        $prestamo = Prestamo::findOrFail($prestamoId);

        // 2) Validar rango de cuota
        $nroCuota    = (int) $request->nro_cuota;
        $totalCuotas = max(1, (int) $prestamo->nro_cuotas);
        if ($nroCuota > $totalCuotas) {
            return back()
                ->with('mensaje', 'El número de cuota no existe para este préstamo.')
                ->with('icono', 'error');
        }

        // 3) Ubicar el registro de pago que representa esa cuota
        //    (tu tabla pagos no tiene nro_cuota, así que usamos orden por id)
        $pago = Pago::where('prestamo_id', $prestamo->id)
            ->orderBy('id')               // orden estable por creación
            ->skip($nroCuota - 1)         // 1->0, 2->1, etc.
            ->take(1)
            ->first();

        if (!$pago) {
            return back()
                ->with('mensaje', 'No se encontró el registro de pago para esa cuota.')
                ->with('icono', 'error');
        }

        // 4) Determinar el VALOR FIJO de la cuota.
        //    ¡Nunca usar pagos.monto_pagado (suele ser acumulado)!
        $montoCuotaFijo = null;
        foreach (['monto_cuota', 'monto', 'valor_cuota', 'total_cuota'] as $col) {
            if (isset($pago->{$col}) && is_numeric($pago->{$col}) && $pago->{$col} > 0) {
                $montoCuotaFijo = (float) $pago->{$col};
                break;
            }
        }

        // Fallback por datos del préstamo (tu caso real: 1.500.000 / 10 = 150.000)
        if (!$montoCuotaFijo || $montoCuotaFijo <= 0) {
            if (
                isset($prestamo->monto_total) &&
                isset($prestamo->nro_cuotas) &&
                (int) $prestamo->nro_cuotas > 0
            ) {
                $montoCuotaFijo = round(
                    ((float) $prestamo->monto_total) / (int) $prestamo->nro_cuotas,
                    2
                );
            }
        }

        if (!$montoCuotaFijo || $montoCuotaFijo <= 0) {
            return back()
                ->with('mensaje', 'No se pudo determinar el valor fijo de la cuota (revisa columnas de pagos o los datos del préstamo).')
                ->with('icono', 'error');
        }

        // 5) Normalizar a "centavos"
        $montoCuotaCents = $toCents($montoCuotaFijo);

        // 6) Transacción
        return DB::transaction(function () use ($request, $prestamo, $pago, $nroCuota, $montoCuotaCents, $toCents, $toPesos) {

            $fechaPago = $request->filled('fecha_pago')
                ? date('Y-m-d', strtotime($request->fecha_pago))
                : now()->toDateString();

            // Total abonado previo a esa cuota (centavos)
            $abonadoActual = (float) Abono::where('prestamo_id', $prestamo->id)
                ->where('nro_cuota', $nroCuota)
                ->selectRaw('COALESCE(SUM(COALESCE(monto, monto_abonado, 0)), 0) as total')
                ->value('total');

            $abonadoActualCents = $toCents($abonadoActual);

            // Restante a cubrir (centavos)
            $restanteCents = max(0, $montoCuotaCents - $abonadoActualCents);

            // Monto ingresado normalizado (centavos) y cap al restante
            $montoIngresoCents = min($toCents($request->monto), $restanteCents);

            if ($montoIngresoCents <= 0) {
                return back()
                    ->with('mensaje', 'La cuota ya está completa o el monto es inválido.')
                    ->with('icono', 'info');
            }

            // 7) Registrar el abono
            $montoIngresoPesos = $toPesos($montoIngresoCents); // guarda en mismo formato de tu BD

            $dataCreate = [
                'prestamo_id'   => $prestamo->id,
                'nro_cuota'     => $nroCuota,
                'monto'         => $montoIngresoPesos,   // esquema nuevo
                'monto_abonado' => $montoIngresoPesos,   // compat esquema antiguo
                'referencia'    => $request->referencia,
                'fecha_pago'    => $fechaPago,
            ];

            if (Schema::hasColumn('abonos', 'user_id')) {
                $dataCreate['user_id'] = auth()->id() ?? null;
            }
            if (Schema::hasColumn('abonos', 'estado')) {
                $dataCreate['estado'] = 'Confirmado';
            }

            Abono::create($dataCreate);

            // 8) Pasar dinero al bucket transitorio (NO caja)
            $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
            if ($empresa && Schema::hasColumn('empresa_capital', 'capital_asignado_total')) {
                $empresa->capital_anterior       = (int) ($empresa->capital_asignado_total ?? 0);
                $empresa->capital_asignado_total = (int) ($empresa->capital_asignado_total ?? 0) + (int) $montoIngresoPesos;
                $empresa->save();

                RegistroCapital::create([
                    'monto'       => (int) $montoIngresoPesos,
                    'user_id'     => auth()->id(),
                    'tipo_accion' => 'Cobro de abono (transitorio asesores) préstamo #'.$prestamo->id.' cuota '.$nroCuota,
                ]);
            }

            // 9) Si se completó la cuota, marcar Pago como Confirmado (comparación en centavos)
            $abonadoTotal = (float) Abono::where('prestamo_id', $prestamo->id)
                ->where('nro_cuota', $nroCuota)
                ->selectRaw('COALESCE(SUM(COALESCE(monto, monto_abonado, 0)), 0) as total')
                ->value('total');

            $abonadoTotalCents = $toCents($abonadoTotal);

            if ($abonadoTotalCents >= $montoCuotaCents) {
                if ($pago->estado !== 'Confirmado') {
                    $pago->estado          = 'Confirmado';
                    $pago->fecha_cancelado = $fechaPago; // fecha del último abono
                    $pago->save();
                }
            }

            return back()
                ->with('mensaje', 'Abono registrado correctamente.')
                ->with('icono', 'success');
        });
    }
}
