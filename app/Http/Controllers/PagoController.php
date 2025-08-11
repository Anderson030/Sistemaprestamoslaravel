<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\EmpresaCapital;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

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

    public function store($id)
    {
        $pago = Pago::find($id);

        // ✅ Evitar duplicado si ya está confirmado
        if ($pago->estado !== "Confirmado") {
            $pago->estado = "Confirmado";
            $pago->fecha_cancelado = date('Y-m-d');
            $pago->save();

            // ✅ ACTUALIZAR CAPITAL EMPRESA
            $capital = EmpresaCapital::first();
            if ($capital) {
                $capital->capital_anterior = $capital->capital_disponible;
$capital->capital_total += $pago->monto_pagado;
$capital->capital_disponible += $pago->monto_pagado;

                $capital->save();
            }

            // ✅ Verificar si es el último pago
            $total_cuotas_faltantes = Pago::where('prestamo_id', $pago->prestamo->id)
                ->where('estado', 'Pendiente')
                ->count();

            if ($total_cuotas_faltantes == 0) {
                $prestamo = Prestamo::find($pago->prestamo->id);
                $prestamo->estado = "Cancelado";
                $prestamo->save();
            }
        }

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

    public function destroy($id)
    {
        $pago = Pago::find($id);

        // ✅ Si el pago estaba confirmado, restar del capital
        if ($pago->estado === "Confirmado") {
            $capital = EmpresaCapital::first();
            if ($capital) {
                $capital->capital_anterior = $capital->capital_disponible;
                $capital->capital_total -= $pago->monto_pagado;
                $capital->capital_disponible -= $pago->monto_pagado;
                $capital->save();
            }
        }

        // Revertir el estado del pago
        $pago->fecha_cancelado = null;
        $pago->estado = "Pendiente";
        $pago->save();

        return redirect()->route('admin.pagos.index')
            ->with('mensaje', 'Se eliminó el pago del cliente de la manera correcta')
            ->with('icono', 'success');
    }
}
