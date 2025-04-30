<?php

namespace App\Http\Controllers;

use App\Mail\NotificarPago;
use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NotificacionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $configuracion = Configuracion::latest()->first();
        $pagos = Pago::orderBy('fecha_pago','asc')->get();
        return view('admin.notificaciones.index',compact('pagos','configuracion'));
    }

    /**
     * Show the form for creating a new resource.
     */

    public function notificar($id){
        $pago = Pago::find($id);
        Mail::to($pago->prestamo->cliente->email)->send(new NotificarPago($pago));

        return redirect()->back()
            ->with('mensaje','Se envio el correo al cliente de la manera correcta')
            ->with('icono','success');
    }
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Notificacion $notificacion)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Notificacion $notificacion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notificacion $notificacion)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notificacion $notificacion)
    {
        //
    }
}
