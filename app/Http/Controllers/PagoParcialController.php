<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\PagoParcial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagoParcialController extends Controller
{
    /**
     * Listado con filtros y paginación.
     * - PRESTAMISTA: solo sus notas.
     * - ADMIN/SUPERVISOR/DEV: todas.
     * Filtros opcionales: q (cedula o nombre).
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $notas = PagoParcial::query()
            ->with(['cliente', 'usuario'])
            ->when(auth()->user()->hasRole('PRESTAMISTA'), function ($q2) {
                $q2->where('idusuario', auth()->id());
            })
            ->when($q !== '', function ($q2) use ($q) {
                $q2->whereHas('cliente', function ($qq) use ($q) {
                    $qq->where('nro_documento', 'like', "%{$q}%")
                       ->orWhere('nombre', 'like', "%{$q}%")
                       ->orWhere('apellidos', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pagos_parciales.index', [
            'pagosParciales' => $notas,
            'q'              => $q,
        ]);
    }

    /**
     * Form para crear. Si necesitas autocompletar por cédula,
     * la vista puede tener un input de cédula y buscar por AJAX.
     */
    public function create()
    {
        return view('admin.pagos_parciales.create');
    }

    /**
     * Guarda una nota de pago parcial (no mueve dinero).
     * Reglas:
     * - cédula debe existir.
     * - PRESTAMISTA solo puede registrar notas de clientes que le pertenecen.
     */
    public function store(Request $request)
    {
        // Normalizamos cédula (sin espacios)
        $request->merge([
            'cedula' => preg_replace('/\s+/', '', (string) $request->input('cedula'))
        ]);

        $request->validate([
            'cedula' => 'required|exists:clientes,nro_documento',
            'nota'   => 'required|string|max:500',
        ], [
            'cedula.exists' => 'No se encontró un cliente con esa cédula.',
        ]);

        // Buscamos cliente
        $cliente = Cliente::where('nro_documento', $request->cedula)->first();

        if (!$cliente) {
            return redirect()
                ->route('admin.pagos_parciales.create')
                ->withInput()
                ->with('mensaje', 'Cliente no encontrado.')
                ->with('icono', 'error');
        }

        // Si es prestamista, debe ser su cliente
        if (auth()->user()->hasRole('PRESTAMISTA')) {
            if ((int) $cliente->idusuario !== (int) auth()->id()) {
                return back()
                    ->withInput()
                    ->with('mensaje', 'No puedes registrar notas para un cliente que no te pertenece.')
                    ->with('icono', 'error');
            }
        }

        PagoParcial::create([
            'cliente_id' => (int) $cliente->id,
            'idusuario'  => (int) auth()->id(),
            'nota'       => trim((string) $request->nota),
        ]);

        return redirect()->route('admin.pagos_parciales.index')
            ->with('mensaje', 'Nota registrada correctamente.')
            ->with('icono', 'success');
    }

    /**
     * Eliminación con control de pertenencia para prestamista.
     */
    public function destroy($id)
    {
        $nota = PagoParcial::with('cliente')->findOrFail($id);

        if (auth()->user()->hasRole('PRESTAMISTA') && (int) $nota->idusuario !== (int) auth()->id()) {
            abort(403, 'No tienes permiso para eliminar esta nota.');
        }

        $nota->delete();

        return redirect()->route('admin.pagos_parciales.index')
            ->with('mensaje', 'Nota eliminada correctamente.')
            ->with('icono', 'success');
    }
}
