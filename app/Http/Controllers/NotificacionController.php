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
     * Muestra la lista de pagos pendientes con sus respectivas relaciones cargadas.
     */
    public function index()
    {
        $configuracion = Configuracion::latest()->first();

        if (auth()->user()->hasRole('PRESTAMISTA')) {
            // Obtener los préstamos del prestamista autenticado
            $idsPrestamos = auth()->user()->prestamos->pluck('id');

            // Se cargan las relaciones prestamo y cliente para evitar errores de propiedad nula
            $pagos = Pago::with('prestamo.cliente')
                         ->whereIn('prestamo_id', $idsPrestamos)
                         ->orderBy('fecha_pago', 'asc')
                         ->get();
        } else {
            // Los demás roles ven todos los pagos con sus relaciones
            $pagos = Pago::with('prestamo.cliente')
                         ->orderBy('fecha_pago', 'asc')
                         ->get();
        }

        return view('admin.notificaciones.index', compact('pagos', 'configuracion'));
    }

    /**
     * Envía un correo al cliente asociado al pago.
     */
    public function notificar($id)
    {
        $pago = Pago::with('prestamo.cliente')->findOrFail($id);

        // Validar que exista el cliente y el email antes de enviar
        if ($pago->prestamo && $pago->prestamo->cliente && $pago->prestamo->cliente->email) {
            Mail::to($pago->prestamo->cliente->email)->send(new NotificarPago($pago));

            return redirect()->back()
                ->with('mensaje', 'Se envió el correo al cliente de manera correcta')
                ->with('icono', 'success');
        } else {
            return redirect()->back()
                ->with('mensaje', 'No se pudo enviar el correo. Cliente o email no encontrado.')
                ->with('icono', 'error');
        }
    }

    // Métodos restantes sin cambios
    public function create() { }
    public function store(Request $request) { }
    public function show(Notificacion $notificacion) { }
    public function edit(Notificacion $notificacion) { }
    public function update(Request $request, Notificacion $notificacion) { }
    public function destroy(Notificacion $notificacion) { }
}
