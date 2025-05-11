<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\PagoParcial;
use Illuminate\Http\Request;

class PagoParcialController extends Controller
{
    public function index()
    {
        if (auth()->user()->hasRole('PRESTAMISTA')) {
            // Solo ve sus propias notas
            $pagosParciales = PagoParcial::where('idusuario', auth()->id())->with('cliente')->get();
        } else {
            // Admin, Supervisor y Dev ven todo
            $pagosParciales = PagoParcial::with('cliente', 'usuario')->get();
        }

        return view('admin.pagos_parciales.index', compact('pagosParciales'));
    }

    public function create()
    {
        return view('admin.pagos_parciales.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'cedula' => 'required|exists:clientes,nro_documento',
            'nota' => 'required|string',
        ]);

        $cliente = Cliente::where('nro_documento', $request->cedula)->first();

        PagoParcial::create([
            'cliente_id' => $cliente->id,
            'idusuario' => auth()->id(),
            'nota' => $request->nota,
        ]);

        return redirect()->route('admin.pagos_parciales.index')
            ->with('mensaje', 'Nota registrada correctamente.')
            ->with('icono', 'success');
    }

    public function destroy($id)
{
    $nota = PagoParcial::findOrFail($id);

    if (auth()->user()->hasRole('PRESTAMISTA') && $nota->idusuario !== auth()->id()) {
        abort(403, 'No tienes permiso para eliminar esta nota.');
    }

    $nota->delete();

    return redirect()->route('admin.pagos_parciales.index')
        ->with('mensaje', 'Nota eliminada correctamente.')
        ->with('icono', 'success');
}

}
