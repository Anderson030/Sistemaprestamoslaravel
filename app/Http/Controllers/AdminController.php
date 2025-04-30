<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function index(){
        $total_configuraciones = Configuracion::count();
        $total_roles = Role::count();
        $total_usuarios = User::count();
        $total_clientes = Cliente::count();
        $total_prestamos = Prestamo::count();
        $total_pagos = Pago::where('estado','Confirmado')->count();

        $prestamos = Prestamo::all();
        $pagos = Pago::where('estado','Confirmado')->get();

        return view('admin.index',compact('total_configuraciones',
            'total_roles',
            'total_usuarios',
            'total_prestamos',
            'total_pagos',
            'total_clientes','prestamos','pagos'));
    }
}
