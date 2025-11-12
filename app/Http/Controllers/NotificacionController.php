<?php

namespace App\Http\Controllers;

use App\Mail\NotificarPago;
use App\Models\Configuracion;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificacionController extends Controller
{
    /**
     * Lista pagos para notificar.
     * - Por defecto solo "Pendiente".
     * - PRESTAMISTA: solo sus préstamos.
     * Filtros opcionales:
     *   estado: Pendiente|Confirmado|Todos (default: Pendiente)
     *   q: busca por nombre/cedula del cliente
     */
    public function index(Request $request)
    {
        $estado = $request->input('estado', 'Pendiente'); // 'Pendiente' | 'Confirmado' | 'Todos'
        $q      = trim((string) $request->input('q', ''));

        $tz = config('app.timezone', 'America/Bogota');
        $today = Carbon::now($tz)->toDateString();

        $pagos = Pago::query()
            ->with(['prestamo.cliente'])
            ->when($estado !== 'Todos', function ($q2) use ($estado) {
                $q2->where('estado', $estado);
            })
            // Visibilidad de PRESTAMISTA: solo préstamos del usuario
            ->when(auth()->user()->hasRole('PRESTAMISTA'), function ($q2) {
                $q2->whereHas('prestamo', function ($qq) {
                    $qq->where('idusuario', auth()->id());
                });
            })
            // Búsqueda por cliente
            ->when($q !== '', function ($q2) use ($q) {
                $q2->whereHas('prestamo.cliente', function ($qq) use ($q) {
                    $qq->where('nro_documento', 'like', "%{$q}%")
                       ->orWhere('nombre', 'like', "%{$q}%")
                       ->orWhere('apellidos', 'like', "%{$q}%");
                });
            })
            ->orderBy('fecha_pago', 'asc')
            ->paginate(30)
            ->withQueryString();

        // Decoramos cada pago con flags de vencimiento (según TZ)
        $pagos->getCollection()->transform(function ($p) use ($tz, $today) {
            $fecha = $p->fecha_pago ? Carbon::parse($p->fecha_pago, $tz)->toDateString() : null;

            $p->vence_hoy   = $fecha === $today;
            $p->vencido     = $fecha !== null && $fecha <  $today && $p->estado !== 'Confirmado';
            $p->vence_pronto= $fecha !== null && $fecha >  $today && Carbon::parse($fecha)->diffInDays($today) <= 3;

            return $p;
        });

        $configuracion = Configuracion::latest()->first();

        return view('admin.notificaciones.index', [
            'pagos'         => $pagos,
            'configuracion' => $configuracion,
            'estado'        => $estado,
            'q'             => $q,
            'today'         => $today,
        ]);
    }

    /**
     * Envía correo al cliente del pago.
     * Si tu Mailable implementa ShouldQueue, esto se encola automáticamente.
     */
    public function notificar($id)
    {
        $pago = Pago::with('prestamo.cliente')->findOrFail($id);

        // Seguridad de visibilidad para PRESTAMISTA
        if (auth()->user()->hasRole('PRESTAMISTA')) {
            if ((int)optional($pago->prestamo)->idusuario !== (int)auth()->id()) {
                return back()->with('mensaje', 'No puedes notificar pagos de otro prestamista.')->with('icono', 'error');
            }
        }

        $cliente = optional($pago->prestamo)->cliente;
        $email   = optional($cliente)->email;

        if (!$cliente || !$email) {
            return back()
                ->with('mensaje', 'No se pudo enviar el correo. Cliente o email no encontrado.')
                ->with('icono', 'error');
        }

        try {
            // Si NotificarPago implements ShouldQueue, usa ->queue(); si no, ->send()
            if (in_array(\Illuminate\Contracts\Queue\ShouldQueue::class, class_implements(NotificarPago::class) ?: [])) {
                Mail::to($email)->queue(new NotificarPago($pago));
            } else {
                Mail::to($email)->send(new NotificarPago($pago));
            }

            return back()
                ->with('mensaje', 'Se envió el correo al cliente de manera correcta')
                ->with('icono', 'success');

        } catch (\Throwable $e) {
            return back()
                ->with('mensaje', 'Error enviando correo: '.$e->getMessage())
                ->with('icono', 'error');
        }
    }

    // Stubs (si no se usan, pueden eliminarse)
    public function create() {}
    public function store(Request $request) {}
    public function show($id) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
}
