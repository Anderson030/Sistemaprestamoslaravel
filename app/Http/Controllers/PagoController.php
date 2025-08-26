<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\EmpresaCapital;
use App\Models\RegistroCapital;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    public function index()
    {
        $clientes = Cliente::all();
        $pagos = Pago::all();
        return view('admin.pagos.index', compact('pagos', 'clientes'));
    }

    public function cargar_prestamos_cliente($id)
    {
        $cliente = Cliente::find($id);
        $prestamos = Prestamo::where('cliente_id', $cliente->id)->get();
        return view('admin.pagos.cargar_prestamos_cliente', compact('cliente', 'prestamos'));
    }

    public function create($id)
    {
        $prestamo = Prestamo::find($id);
        $pagos = Pago::where('prestamo_id', $id)->get();
        return view('admin.pagos.create', compact('prestamo', 'pagos'));
    }

    public function cargar_datos($id)
    {
        $datosCliente = Cliente::find($id);
        $clientes = Cliente::all();
        return view('admin.pagos.cargar_datos', compact('datosCliente', 'clientes'));
    }

    /**
     * Confirma un pago (por id de pago).
     * - Suma a caja (capital_disponible) y capital_total.
     * - Registra en registro_capital.
     * - Si ya estaba confirmado, no hace nada.
     */
    public function store($id)
    {
        $pago = Pago::findOrFail($id);

        // Ya confirmado => salida limpia
        if ($pago->estado === 'Confirmado') {
            return redirect()->back()
                ->with('mensaje', 'El pago ya estaba confirmado.')
                ->with('icono', 'info');
        }

        DB::transaction(function () use ($pago) {
            // 1) Confirmar pago
            $pago->estado = 'Confirmado';
            $pago->fecha_cancelado = date('Y-m-d');
            $pago->save();

            // 2) Actualizar caja (empresa_capital)
            $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
            if ($empresa) {
                $nuevoDisponible = (int)$empresa->capital_disponible + (int)$pago->monto_pagado;

                $empresa->capital_anterior   = $empresa->capital_disponible;
                $empresa->capital_disponible = $nuevoDisponible;
                $empresa->capital_total      = (int)$empresa->capital_total + (int)$pago->monto_pagado; // si manejas total acumulado
                $empresa->save();
            }

            // 3) Log de movimiento
            RegistroCapital::create([
                'monto'       => (int)$pago->monto_pagado,
                'user_id'     => auth()->id(),
                'tipo_accion' => 'Pago confirmado ingresa a caja (préstamo #'.$pago->prestamo_id.')',
            ]);

            // 4) ¿Fue la última cuota?
            $faltantes = Pago::where('prestamo_id', $pago->prestamo_id)
                ->where('estado', 'Pendiente')
                ->count();

            if ($faltantes === 0) {
                $prestamo = Prestamo::find($pago->prestamo_id);
                if ($prestamo) {
                    $prestamo->estado = 'Cancelado';
                    $prestamo->save();
                }
            }
        });

        return redirect()->back()
            ->with('mensaje', 'Se registró el pago de la manera correcta')
            ->with('icono', 'success');
    }

    public function comprobantedepago($id)
    {
        $pago = Pago::find($id);
        $prestamo = Prestamo::where('id', $pago->prestamo_id)->first();
        $cliente = Cliente::where('id', $prestamo->cliente_id)->first();
        $fecha_cancelado = $pago->fecha_cancelado;
        $timestamp = strtotime($fecha_cancelado);
        $dia = date('j', $timestamp);
        $mes = date('F', $timestamp);
        $ano = date('Y', $timestamp);

        $meses = [
            'January' => 'enero',
            'February' => 'febrero',
            'March' => 'marzo',
            'April' => 'abril',
            'May' => 'mayo',
            'June' => 'junio',
            'July' => 'julio',
            'August' => 'agosto',
            'September' => 'septiembre',
            'October' => 'octubre',
            'November' => 'noviembre',
            'December' => 'diciembre',
        ];

        $mes_espanol = $meses[$mes];
        $fecha_literal = $dia . " de " . $mes_espanol . " de " . $ano;

        $configuracion = Configuracion::latest()->first();
        $pdf = PDF::loadView('admin.pagos.comprobantedepago', compact('pago', 'configuracion', 'fecha_literal', 'prestamo', 'cliente'));
        return $pdf->stream();
    }

    public function show($id)
    {
        $pago = Pago::find($id);
        $prestamo = Prestamo::where('id', $pago->prestamo_id)->first();
        $cliente = Cliente::where('id', $prestamo->cliente_id)->first();

        return view('admin.pagos.show', compact('pago', 'prestamo', 'cliente'));
    }

    public function edit(Pago $pago)
    {
        //
    }

    public function update(Request $request, Pago $pago)
    {
        //
    }

    /**
     * Revierte la confirmación de un pago:
     * - Resta de caja y capital_total.
     * - Deja el pago como Pendiente.
     * - Deja traza en registro_capital.
     */
    public function destroy($id)
    {
        $pago = Pago::findOrFail($id);

        DB::transaction(function () use ($pago) {
            // Si estaba confirmado, revertir caja
            if ($pago->estado === 'Confirmado') {
                $empresa = EmpresaCapital::query()->lockForUpdate()->latest('id')->first();
                if ($empresa) {
                    $empresa->capital_anterior   = $empresa->capital_disponible;
                    $empresa->capital_total      = (int)$empresa->capital_total - (int)$pago->monto_pagado;
                    $empresa->capital_disponible = (int)$empresa->capital_disponible - (int)$pago->monto_pagado;
                    if ($empresa->capital_disponible < 0) {
                        $empresa->capital_disponible = 0; // por seguridad
                    }
                    $empresa->save();
                }

                RegistroCapital::create([
                    'monto'       => (int)$pago->monto_pagado * -1,
                    'user_id'     => auth()->id(),
                    'tipo_accion' => 'Reverso de pago confirmado (préstamo #'.$pago->prestamo_id.')',
                ]);
            }

            // Revertir estado del pago
            $pago->fecha_cancelado = null;
            $pago->estado = 'Pendiente';
            $pago->save();

            // Si el préstamo quedó con cuotas pendientes, marcarlo Pendiente (por consistencia)
            $faltantes = Pago::where('prestamo_id', $pago->prestamo_id)
                ->where('estado', 'Pendiente')
                ->count();

            if ($faltantes > 0) {
                $prestamo = Prestamo::find($pago->prestamo_id);
                if ($prestamo) {
                    $prestamo->estado = 'Pendiente';
                    $prestamo->save();
                }
            }
        });

        return redirect()->route('admin.pagos.index')
            ->with('mensaje', 'Se eliminó el pago del cliente de la manera correcta')
            ->with('icono', 'success');
    }
}
