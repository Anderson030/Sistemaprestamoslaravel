<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\CapitalEmpresa;
use App\Models\CapitalPrestamista;
use App\Models\MovimientoCapitalPrestamista;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PrestamoController extends Controller
{
    public function create()
    {
        if (auth()->user()->hasRole('PRESTAMISTA')) {
            $clientes = Cliente::where('idusuario', auth()->id())->get();
        } else {
            $clientes = Cliente::all();
        }

        return view('admin.prestamos.create', compact('clientes'));
    }

    public function index()
    {
        if (auth()->user()->hasRole('PRESTAMISTA')) {
            $prestamos = Prestamo::where('idusuario', auth()->id())->get();
        } else {
            $prestamos = Prestamo::all();
        }

        foreach ($prestamos as $prestamo) {
            $prestamo->tiene_cuota_pagada = Pago::whereNotNull('fecha_cancelado')
                ->where('prestamo_id', $prestamo->id)
                ->exists();
        }

        return view('admin.prestamos.index', compact('prestamos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required',
            'monto_prestado' => 'required',
            'tasa_interes' => 'required',
            'modalidad' => 'required',
            'nro_cuotas' => 'required',
            'fecha_inicio' => 'required',
            'monto_total' => 'required',
            'monto_cuota' => 'required',
        ]);

        $prestamo = new Prestamo();
        $prestamo->cliente_id = $request->cliente_id;
        $prestamo->monto_prestado = $request->monto_prestado;
        $prestamo->tasa_interes = $request->tasa_interes;
        $prestamo->modalidad = $request->modalidad;
        $prestamo->nro_cuotas = $request->nro_cuotas;
        $prestamo->fecha_inicio = $request->fecha_inicio;
        $prestamo->monto_total = $request->monto_total;
        $prestamo->idusuario = auth()->id();
        $prestamo->save();

        for ($i = 1; $i <= $request->nro_cuotas; $i++) {
            $pago = new Pago();
            $pago->prestamo_id = $prestamo->id;
            $pago->monto_pagado = $request->monto_cuota;

            $fechaInicio = Carbon::parse($request->fecha_inicio);
            switch ($request->modalidad) {
                case 'Diario': $fechaVencimiento = $fechaInicio->copy()->addDays($i); break;
                case 'Semanal': $fechaVencimiento = $fechaInicio->copy()->addWeeks($i); break;
                case 'Quincenal': $fechaVencimiento = $fechaInicio->copy()->addWeeks($i * 2 - 1); break;
                case 'Mensual': $fechaVencimiento = $fechaInicio->copy()->addMonths($i); break;
                case 'Anual': $fechaVencimiento = $fechaInicio->copy()->addYears($i); break;
            }

            $pago->fecha_pago = $fechaVencimiento;
            $pago->metodo_pago = "Efectivo";
            $pago->referencia_pago = "Pago de la cuota " . $i;
            $pago->estado = "Pendiente";
            $pago->save();
        }

        $usuario = auth()->user();

        if ($usuario->hasRole(['ADMINISTRADOR', 'SUPERVISOR', 'PRESTAMISTA'])) {
            $capitalPrestamista = CapitalPrestamista::firstOrCreate(['user_id' => $usuario->id]);

            if ($capitalPrestamista->monto_disponible < $request->monto_prestado) {
                return redirect()->back()
                    ->with('mensaje', 'No tienes suficiente capital disponible para prestar este monto.')
                    ->with('icono', 'error');
            }

            $capitalPrestamista->monto_disponible -= $request->monto_prestado;
             $capitalPrestamista->monto_asignado -= $request->monto_prestado;
            $capitalPrestamista->save();

            MovimientoCapitalPrestamista::create([
                'user_id' => $usuario->id,
                'monto' => $request->monto_prestado,
                'descripcion' => 'Préstamo realizado ID ' . $prestamo->id,
            ]);
        }

        return redirect()->route('admin.prestamos.index')
            ->with('mensaje', 'Se registró el préstamo correctamente')
            ->with('icono', 'success');
    }

    public function obtenerCliente($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        return response()->json($cliente);
    }

    public function destroy($id)
    {
        $prestamo = Prestamo::findOrFail($id);

        if (auth()->user()->hasRole('PRESTAMISTA') && $prestamo->idusuario !== auth()->id()) {
            return redirect()->route('admin.prestamos.index')
                ->with('mensaje', 'No tienes permiso para eliminar este préstamo.')
                ->with('icono', 'error');
        }

        $prestamo->delete();

        return redirect()->route('admin.prestamos.index')
            ->with('mensaje', 'Préstamo eliminado correctamente.')
            ->with('icono', 'success');
    }

    public function contratos($id)
{
    $prestamo = Prestamo::with('cliente')->findOrFail($id);
    $configuracion = Configuracion::first();
    $pagos = Pago::where('prestamo_id', $prestamo->id)->get();

    $pdf = Pdf::loadView('admin.prestamos.contratos', compact('prestamo', 'configuracion', 'pagos'));

    return $pdf->download('prestamo_' . $prestamo->id . '.pdf');
}
public function show($id)
{
    $prestamo = Prestamo::with('cliente')->findOrFail($id);
    $pagos = Pago::where('prestamo_id', $prestamo->id)->get();

    return view('admin.prestamos.show', compact('prestamo', 'pagos'));
}

}
