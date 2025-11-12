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
use Carbon\Carbon;

class AbonoController extends Controller
{
    /**
     * Registra un abono (pago parcial) a una cuota específica de un préstamo.
     * - Nunca marca como completa si el total abonado < valor fijo de la cuota.
     * - Si completa, confirma el Pago y fija pagos.monto_pagado = valor fijo de la cuota (en pesos).
     * - El dinero va al bucket transitorio: empresa.capital_asignado_total (no a caja).
     */
    public function store(Request $request, $prestamoId)
    {
        // 0) Validación
        $request->validate([
            'nro_cuota'  => 'required|integer|min:1',
            // Permitimos "150.000", "150,000" o "150000"
            'monto'      => ['required', 'string', 'max:50'],
            'referencia' => 'nullable|string|max:255',
            // nullable: si no viene, se usa hoy (DATE local)
            'fecha_pago' => 'nullable|date',
        ]);

        // Helpers de normalización
        $toCents = function ($val) {
            if ($val === null) return 0;
            $clean = preg_replace('/[^\d]/', '', (string)$val); // “150.000,50” -> “15000050”
            return (int) $clean;
        };
        // Trabajamos en pesos enteros; si usaras decimales reales, cambia a dividir /100
        $toPesos = fn (int $cents) => (float) $cents;

        // 1) Préstamo
        $prestamo = Prestamo::findOrFail($prestamoId);

        // 2) Validar cuota
        $nroCuota    = (int) $request->nro_cuota;
        $totalCuotas = max(1, (int) $prestamo->nro_cuotas);
        if ($nroCuota > $totalCuotas) {
            return back()->with('mensaje', 'El número de cuota no existe para este préstamo.')->with('icono', 'error');
        }

        // 3) Ubicar el Pago (no hay nro_cuota en tabla; usamos orden por id)
        $pago = Pago::where('prestamo_id', $prestamo->id)
            ->orderBy('id')
            ->skip($nroCuota - 1)
            ->first();

        if (!$pago) {
            return back()->with('mensaje', 'No se encontró el registro de pago para esa cuota.')->with('icono', 'error');
        }

        // 4) Valor FIJO de la cuota (en pesos)
        $montoCuotaFijo = null;
        foreach (['monto_cuota', 'monto', 'valor_cuota', 'total_cuota'] as $col) {
            if (isset($pago->{$col}) && is_numeric($pago->{$col}) && $pago->{$col} > 0) {
                $montoCuotaFijo = (float) $pago->{$col};
                break;
            }
        }
        if (!$montoCuotaFijo || $montoCuotaFijo <= 0) {
            if (
                isset($prestamo->monto_total) &&
                isset($prestamo->nro_cuotas) &&
                (int) $prestamo->nro_cuotas > 0
            ) {
                $montoCuotaFijo = round(((float) $prestamo->monto_total) / (int) $prestamo->nro_cuotas, 2);
            }
        }
        if (!$montoCuotaFijo || $montoCuotaFijo <= 0) {
            return back()->with('mensaje', 'No se pudo determinar el valor fijo de la cuota.')->with('icono', 'error');
        }

        $montoCuotaCents = $toCents($montoCuotaFijo);

        // 5) Transacción
        return DB::transaction(function () use ($request, $prestamo, $pago, $nroCuota, $montoCuotaCents, $toCents, $toPesos) {

            // Fecha local para el abono (DATE para UI/reportes)
            $fechaPagoLocal = $request->filled('fecha_pago')
                ? Carbon::parse($request->fecha_pago, 'America/Bogota')->toDateString()
                : Carbon::now('America/Bogota')->toDateString();

            // 6) Total abonado previo a esa cuota
            $abonadoActual = (float) Abono::where('prestamo_id', $prestamo->id)
                ->where('nro_cuota', $nroCuota)
                ->selectRaw('COALESCE(SUM(COALESCE(monto, monto_abonado, 0)), 0) as total')
                ->value('total');

            $abonadoActualCents = $toCents($abonadoActual);
            $restanteCents      = max(0, $montoCuotaCents - $abonadoActualCents);

            // 7) Monto ingresado normalizado (cap al restante)
            $montoIngresoCents = min($toCents($request->monto), $restanteCents);
            if ($montoIngresoCents <= 0) {
                return back()->with('mensaje', 'La cuota ya está completa o el monto es inválido.')->with('icono', 'info');
            }
            $montoIngresoPesos = $toPesos($montoIngresoCents);

            // 8) Registrar el abono
            $dataCreate = [
                'prestamo_id'   => $prestamo->id,
                'nro_cuota'     => $nroCuota,
                'monto'         => $montoIngresoPesos,   // esquema nuevo (en pesos)
                'monto_abonado' => $montoIngresoPesos,   // compat esquema antiguo (en pesos)
                'referencia'    => $request->referencia,
                'fecha_pago'    => $fechaPagoLocal,      // DATE local (para UI)
            ];
            if (Schema::hasColumn('abonos', 'user_id')) {
                $dataCreate['user_id'] = auth()->id() ?? null;
            }
            if (Schema::hasColumn('abonos', 'estado')) {
                $dataCreate['estado'] = 'Confirmado';
            }
            Abono::create($dataCreate);

            // 9) Sumar al bucket de ruta (NO caja) con clamp
            $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
            if ($empresa && Schema::hasColumn('empresa_capital', 'capital_asignado_total')) {
                $actual = max(0, (int) ($empresa->capital_asignado_total ?? 0));
                $empresa->capital_asignado_total = $actual + (int) $montoIngresoPesos;
                $empresa->save();

                RegistroCapital::create([
                    'monto'       => (int) $montoIngresoPesos,
                    'user_id'     => auth()->id(),
                    'tipo_accion' => 'Cobro de abono (transitorio asesores) préstamo #'.$prestamo->id.' cuota '.$nroCuota,
                ]);
            }

            // 10) ¿Se completó la cuota con este abono? (comparación en centavos)
            $abonadoTotal = (float) Abono::where('prestamo_id', $prestamo->id)
                ->where('nro_cuota', $nroCuota)
                ->selectRaw('COALESCE(SUM(COALESCE(monto, monto_abonado, 0)), 0) as total')
                ->value('total');

            $abonadoTotalCents = $toCents($abonadoTotal);

            if ($abonadoTotalCents >= $montoCuotaCents) {
                if ($pago->estado !== 'Confirmado') {
                    $pago->estado = 'Confirmado';

                    // Guardar fecha_cancelado como fin de día local convertido a UTC
                    // => Auditorías (CONVERT_TZ) lo verá en el mismo día local del abono
                    $pago->fecha_cancelado = Carbon::parse($fechaPagoLocal . ' 23:59:59', 'America/Bogota')->timezone('UTC');

                    // MUY IMPORTANTE: fija el monto_pagado = valor fijo de la cuota (en pesos)
                    if (Schema::hasColumn('pagos', 'monto_pagado')) {
                        $pago->monto_pagado = (float) ($montoCuotaCents); // en tu esquema trabajas pesos enteros (coherente con toPesos)
                    }
                    $pago->save();
                }
            }

            return back()->with('mensaje', 'Abono registrado correctamente.')->with('icono', 'success');
        });
    }
}
