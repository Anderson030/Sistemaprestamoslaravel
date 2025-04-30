@extends('adminlte::page')

@section('content_header')
    <h1><b>Roles/Asignar permisos al {{$rol->name}}</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Lleno los datos del formulario</h3>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <form action="{{url('admin/roles/asignar',$rol->id)}}" method="post">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    @foreach($permisos as $modulo => $grupoPermisos )
                                    <div class="col-md-4">
                                        <h3>{{$modulo}}</h3>
                                        @foreach($grupoPermisos as $permiso)
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="permisos[]" value="{{$permiso->id}}"
                                                {{$rol->hasPermissionTo($permiso->name) ? 'checked': ''}} >
                                                <label for="" class="form-check-label">{{$permiso->name}}</label>
                                            </div>
                                        @endforeach
                                        <br>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <a href="{{url('/admin/roles')}}" class="btn btn-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">Registrar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>
@stop

@section('css')

@stop

@section('js')

@stop
