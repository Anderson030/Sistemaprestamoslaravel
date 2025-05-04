<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(){
        $usuarios = User::all();
        return view('admin.usuarios.index',compact('usuarios'));
    }

    public function create(){
        $roles = Role::all();
        return view('admin.usuarios.create',compact('roles'));
    }

    public function store(Request $request){
        //$datos = request()->all();
        //return response()->json($datos);
        $request->validate([
            'name' => 'required',
            'email' => 'required|unique:users',
            'password' => 'required|confirmed',
        ]);

        $usuario = new User();
        $usuario->name = $request->name;
        $usuario->email = $request->email;
        $usuario->password = Hash::make($request->password);
        $usuario->save();

        $usuario->assignRole($request->rol);

        return redirect()->route('admin.usuarios.index')
            ->with('mensaje','Se registro al usuario de la manera correcta')
            ->with('icono','success');
    }

    public function show($id){
        $usuario = User::find($id);
        return view('admin.usuarios.show',compact('usuario'));
    }

    public function edit($id){
        $roles = Role::all();
        $usuario = User::find($id);
        return view('admin.usuarios.edit',compact('usuario','roles'));
    }

    public function update(Request $request, $id){
       // $datos = request()->all();
       // return response()->json($datos);
        $request->validate([
            'name' => 'required',
            'email' => 'required|unique:users,email,'.$id,
            'password' => 'confirmed',
        ]);

        $usuario = User::find($id);
        $usuario->name = $request->name;
        $usuario->email = $request->email;

        if($request->filled('password')){
            $usuario->password = Hash::make($request->password);
        }

        $usuario->save();

        $usuario->syncRoles($request->rol);

        return redirect()->route('admin.usuarios.index')
            ->with('mensaje','Se modifico al usuario de la manera correcta')
            ->with('icono','success');
    }

    public function destroy($id){
        User::destroy($id);
        return redirect()->route('admin.usuarios.index')
            ->with('mensaje','Se elimino al usuario de la manera correcta')
            ->with('icono','success');
    }
}
