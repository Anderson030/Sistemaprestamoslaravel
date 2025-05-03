<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index()
    {
        $clientes = Cliente::all();
        return view('admin.clientes.index', compact('clientes'));
    }

    public function create()
    {
        return view('admin.clientes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nro_documento' => 'required|unique:clientes',
            'nombres' => 'required',
            'apellidos' => 'required',
            'fecha_nacimiento' => 'required',
            'email' => 'required',
            'celular' => 'required',
            'ref_celular' => 'required',
        ]);

        $cliente = new Cliente();
        $cliente->nro_documento = $request->nro_documento;
        $cliente->nombres = $request->nombres;
        $cliente->apellidos = $request->apellidos;
        $cliente->fecha_nacimiento = $request->fecha_nacimiento;
        $cliente->genero = $request->genero;
        $cliente->email = $request->email;
        $cliente->celular = $request->celular;
        $cliente->ref_celular = $request->ref_celular;

        // Nuevos campos
        $cliente->nombre_referencia1 = $request->nombre_referencia1;
        $cliente->telefono_referencia1 = $request->telefono_referencia1;
        $cliente->nombre_referencia2 = $request->nombre_referencia2;
        $cliente->telefono_referencia2 = $request->telefono_referencia2;

        $cliente->save();

        return redirect()->route('admin.clientes.index')
            ->with('mensaje', 'Se registró al cliente de la manera correcta')
            ->with('icono', 'success');
    }

    public function show($id)
    {
        $cliente = Cliente::find($id);
        return view('admin.clientes.show', compact('cliente'));
    }

    public function edit($id)
    {
        $cliente = Cliente::find($id);
        return view('admin.clientes.edit', compact('cliente'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nro_documento' => 'required|unique:clientes,nro_documento,' . $id,
            'nombres' => 'required',
            'apellidos' => 'required',
            'fecha_nacimiento' => 'required',
            'email' => 'required',
            'celular' => 'required',
            'ref_celular' => 'required',
        ]);

        $cliente = Cliente::find($id);
        $cliente->nro_documento = $request->nro_documento;
        $cliente->nombres = $request->nombres;
        $cliente->apellidos = $request->apellidos;
        $cliente->fecha_nacimiento = $request->fecha_nacimiento;
        $cliente->genero = $request->genero;
        $cliente->email = $request->email;
        $cliente->celular = $request->celular;
        $cliente->ref_celular = $request->ref_celular;

        // Nuevos campos
        $cliente->nombre_referencia1 = $request->nombre_referencia1;
        $cliente->telefono_referencia1 = $request->telefono_referencia1;
        $cliente->nombre_referencia2 = $request->nombre_referencia2;
        $cliente->telefono_referencia2 = $request->telefono_referencia2;

        $cliente->save();

        return redirect()->route('admin.clientes.index')
            ->with('mensaje', 'Se modificó el cliente de la manera correcta')
            ->with('icono', 'success');
    }

    public function destroy($id)
    {
        Cliente::destroy($id);
        return redirect()->route('admin.clientes.index')
            ->with('mensaje', 'Se eliminó al cliente de la manera correcta')
            ->with('icono', 'success');
    }
}
