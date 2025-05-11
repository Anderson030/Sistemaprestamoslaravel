<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;


class PagoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clientes = Cliente::all();
        $pagos = Pago::all();
        return view('admin.pagos.index',compact('pagos','clientes'));
    }

    /**
     * Show the form for creating a new resource.
     */

    public function cargar_prestamos_cliente($id){
        $cliente = Cliente::find($id);
        $prestamos = Prestamo::where('cliente_id',$cliente->id)->get();
        return view('admin.pagos.cargar_prestamos_cliente',compact('cliente','prestamos'));
    }




    public function create($id)
    {
        $prestamo = Prestamo::find($id);
        $pagos = Pago::where('prestamo_id',$id)->get();
        return view('admin.pagos.create',compact('prestamo','pagos'));
    }

    /**
     * Store a newly created resource in storage.
     */

    public function cargar_datos($id){

        $datosCliente = Cliente::find($id);

        $clientes = Cliente::all();

        //$prestamo = Prestamo::where('cliente_id');

        //$pagos = Pago::where('prestamo_id',$prestamo->id)->get();
        return view('admin.pagos.cargar_datos',compact('datosCliente','clientes'));
    }


    public function store($id)
    {
        $pago = Pago::find($id);
        $pago->estado = "Confirmado";
        $pago->fecha_cancelado= date('Y-m-d');
        $pago->save();

        $total_cuotas_faltantes = Pago::where('prestamo_id',$pago->prestamo->id)
            ->where('estado','Pendiente')
            ->count();

        if($total_cuotas_faltantes == 0){
            //echo "ya pago todo";
            $prestamo = Prestamo::find($pago->prestamo->id);
            $prestamo->estado = "Cancelado";
            $prestamo->save();
        }else{
            //echo "falta pagar cuotas";
        }

        return redirect()->back()
            ->with('mensaje','Se registro el pago de la manera correcta')
            ->with('icono','success');


    }

    public function comprobantedepago($id){

        $pago = Pago::find($id);
        $prestamo = Prestamo::where('id',$pago->prestamo_id)->first();
        $cliente = Cliente::where('id',$prestamo->cliente_id)->first();
        $fecha_cancelado = $pago->fecha_cancelado;
        $timestamp = strtotime($fecha_cancelado);
        $dia = date('j',$timestamp);
        $mes = date('F',$timestamp);
        $ano = date('Y',$timestamp);

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

        $fecha_literal = $dia." de ".$mes_espanol." de ".$ano;

        $configuracion = Configuracion::latest()->first();
        $pdf = PDF::loadView('admin.pagos.comprobantedepago',compact('pago','configuracion','fecha_literal','prestamo','cliente'));
        return $pdf->stream();

    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $pago = Pago::find($id);
        $prestamo = Prestamo::where('id',$pago->prestamo_id)->first();
        $cliente = Cliente::where('id',$prestamo->cliente_id)->first();

        return view('admin.pagos.show',compact('pago','prestamo','cliente'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pago $pago)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pago $pago)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $pago = Pago::find($id);
        $pago->fecha_cancelado = null;
        $pago->estado = "Pendiente";
        $pago->save();

        return redirect()->route('admin.pagos.index')
            ->with('mensaje','Se elimino el pago del cliente de la manera correcta')
            ->with('icono','success');

    }
}