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
    public function store(Request $request, $prestamoId)
    {
        // Validación básica
        $request->validate([
            'nro_cuota'  => 'required|integer|min:1',
            'monto'      => 'required|numeric|min:0.01',
            'referencia' => 'nullable|string|max:255',
            'fecha_pago' => 'nullable|date',
        ]);

        // 1) Cargar préstamo
        $prestamo = Prestamo::findOrFail($prestamoId);

        // 2) Validar rango de cuota y obtener el PAGO real de esa cuota
        $nroCuota = (int) $request->nro_cuota;
        $totalCuotas = max(1, (int) $prestamo->nro_cuotas);
        if ($nroCuota > $totalCuotas) {
            return redirect()->back()
                ->with('mensaje', 'El número de cuota no existe para este préstamo.')
                ->with('icono', 'error');
        }

        // Recupera el registro de pago de esa cuota (orden por fecha de pago)
        $pago = Pago::where('prestamo_id', $prestamo->id)
            ->orderBy('fecha_pago')
            ->skip($nroCuota - 1)
            ->take(1)
            ->first();

        if (!$pago) {
            return redirect()->back()
                ->with('mensaje', 'No se encontró el registro de pago para esa cuota.')
                ->with('icono', 'error');
        }

        // Valor real de la cuota (más robusto que dividir el total)
        $montoCuota = (float) $pago->monto_pagado;

        // 3) Transacción
        return DB::transaction(function () use ($request, $prestamo, $pago, $nroCuota, $montoCuota) {

            $fechaPago = $request->filled('fecha_pago')
                ? date('Y-m-d', strtotime($request->fecha_pago))
                : now()->toDateString();

            // Total abonado previo a esa cuota
            $abonadoActual = (float) Abono::where('prestamo_id', $prestamo->id)
                ->where('nro_cuota', $nroCuota)
                ->selectRaw('COALESCE(SUM(COALESCE(monto, monto_abonado, 0)), 0) as total')
                ->value('total');

            // Restante a cubrir en la cuota
            $restante = max(0, round($montoCuota - $abonadoActual, 2));

            // Ajustar el monto si supera el restante
            $montoIngreso = round((float) $request->monto, 2);
            if ($montoIngreso > $restante) {
                $montoIngreso = $restante;
            }

            if ($montoIngreso <= 0) {
                return redirect()->back()
                    ->with('mensaje', 'La cuota ya está completa o el monto es inválido.')
                    ->with('icono', 'info');
            }

            // 1) Registrar el abono
            $dataCreate = [
                'prestamo_id'   => $prestamo->id,
                'nro_cuota'     => $nroCuota,
                'monto'         => $montoIngreso,   // monto guardado (nuevo esquema)
                'monto_abonado' => $montoIngreso,   // compatibilidad con esquema antiguo
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

            // 2) Pasar el dinero cobrado al bucket transitorio (NO a caja)
            $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
            if ($empresa && Schema::hasColumn('empresa_capital', 'capital_asignado_total')) {
                $empresa->capital_anterior       = (int) ($empresa->capital_asignado_total ?? 0);
                $empresa->capital_asignado_total = (int) ($empresa->capital_asignado_total ?? 0) + (int) $montoIngreso;
                $empresa->save();

                RegistroCapital::create([
                    'monto'       => (int) $montoIngreso,
                    'user_id'     => auth()->id(),
                    'tipo_accion' => 'Cobro de abono (transitorio asesores) préstamo #'.$prestamo->id.' cuota '.$nroCuota,
                ]);
            }

            // 3) Si se completó la cuota, marcar Pago como Confirmado
            $abonadoTotal = (float) Abono::where('prestamo_id', $prestamo->id)
                ->where('nro_cuota', $nroCuota)
                ->selectRaw('COALESCE(SUM(COALESCE(monto, monto_abonado, 0)), 0) as total')
                ->value('total');

            if (round($abonadoTotal + 0.01, 2) >= round($montoCuota, 2)) {
                if ($pago->estado !== 'Confirmado') {
                    $pago->estado          = 'Confirmado';
                    $pago->fecha_cancelado = $fechaPago; // fecha del último abono
                    $pago->save();
                }
            }

            return redirect()->back()
                ->with('mensaje', 'Abono registrado correctamente.')
                ->with('icono', 'success');
        });
    }
}
