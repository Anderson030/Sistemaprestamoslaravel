<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::all();
        return view('admin.roles.index',compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.roles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         //$datos = request()->all();
         //return response()->json($datos);

        $request->validate([
            'name' => 'required',
        ]);

        $rol = new Role();
        $rol->name = $request->name;
        $rol->save();

        return redirect()->route('admin.roles.index')
            ->with('mensaje','Se registro el rol de la manera correcta')
            ->with('icono','success');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $rol = Role::find($id);
        return view('admin.roles.show',compact('rol'));
    }

    public function asignar_roles($id){
        $rol = Role::find($id);

        $permisos = Permission::all()->groupBy(function($permiso) {
            if (stripos($permiso->name, 'config') !== false) {
                return 'Configuración';
            } elseif (stripos($permiso->name, 'rol') !== false) {
                return 'Roles';
            } elseif (stripos($permiso->name, 'usu') !== false) {
                return 'Usuarios';
            }elseif (stripos($permiso->name, 'cliente') !== false) {
                return 'Clientes';
            }elseif (stripos($permiso->name, 'presta') !== false) {
                return 'Prestamos';
            }elseif (stripos($permiso->name, 'pago') !== false) {
                return 'Pagos';
            }elseif (stripos($permiso->name, 'notifi') !== false) {
                return 'Notificiones';
            }

        });
        return view('admin.roles.asignar',compact('rol','permisos'));
    }

    public function update_asignar(Request $request, $id){
        $rol = Role::find($id);
        $rol->permissions()->sync($request->input('permisos'));

        return redirect()->route('admin.roles.index')
            ->with('mensaje','Se actualizó los permisos de el rol de la manera correcta')
            ->with('icono','success');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $rol = Role::find($id);
        return view('admin.roles.edit',compact('rol'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //$datos = request()->all();
        //return response()->json($datos);
        $request->validate([
            'name' => 'required',
        ]);

        $rol = Role::find($id);
        $rol->name = $request->name;
        $rol->save();

        return redirect()->route('admin.roles.index')
            ->with('mensaje','Se modifico el rol de la manera correcta')
            ->with('icono','success');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Role::destroy($id);

        return redirect()->route('admin.roles.index')
            ->with('mensaje','Se elimino el rol de la manera correcta')
            ->with('icono','success');
    }
}
