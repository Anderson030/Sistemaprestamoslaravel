@extends('adminlte::page')

@section('content_header')
    <h1><b>Clientes/Modificar datos del cliente</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title">Lleno los datos del formulario</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.clientes.update', $cliente->id) }}" method="post">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Documento</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        </div>
                                        <input type="text" class="form-control" value="{{ $cliente->nro_documento }}" name="nro_documento" required>
                                    </div>
                                    @error('nro_documento')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Nombres del cliente</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        </div>
                                        <input type="text" class="form-control" value="{{ $cliente->nombres }}" name="nombres" required>
                                    </div>
                                    @error('nombres')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Apellidos del cliente</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        </div>
                                        <input type="text" class="form-control" value="{{ $cliente->apellidos }}" name="apellidos" required>
                                    </div>
                                    @error('apellidos')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Fecha de ingreso a la plataforma</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                        </div>
                                        <input type="date" class="form-control" value="{{ $cliente->fecha_nacimiento }}" name="fecha_nacimiento" required>
                                    </div>
                                    @error('fecha_nacimiento')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Categoría</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user-check"></i></span>
                                        </div>
                                        <select name="categoria" class="form-control" required>
                                            <option value="Nuevo" {{ $cliente->categoria == 'Nuevo' ? 'selected' : '' }}>Nuevo</option>
                                            <option value="Bronce" {{ $cliente->categoria == 'Bronce' ? 'selected' : '' }}>Bronce</option>
                                            <option value="Plata" {{ $cliente->categoria == 'Plata' ? 'selected' : '' }}>Plata</option>
                                            <option value="Oro" {{ $cliente->categoria == 'Oro' ? 'selected' : '' }}>Oro</option>
                                        </select>
                                    </div>
                                    @error('categoria')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Email</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" value="{{ $cliente->email }}" name="email" required>
                                    </div>
                                    @error('email')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Celular</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        </div>
                                        <input type="number" class="form-control" value="{{ $cliente->celular }}" name="celular" required>
                                    </div>
                                    @error('celular')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Referencia Celular</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        </div>
                                        <input type="number" class="form-control" value="{{ $cliente->ref_celular }}" name="ref_celular" required>
                                    </div>
                                    @error('ref_celular')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
    <div class="col-md-6">
        <label for="">Dirección</label>
        <input type="text" class="form-control" name="direccion" value="{{ $cliente->direccion }}" required>
    </div>
    <div class="col-md-6">
        <label for="">Nombre Referencia 1</label>
        <input type="text" class="form-control" name="nombre_referencia1" value="{{ $cliente->nombre_referencia1 }}">
    </div>
    <div class="col-md-6">
        <label for="">Teléfono Referencia 1</label>
        <input type="text" class="form-control" name="telefono_referencia1" value="{{ $cliente->telefono_referencia1 }}">
    </div>
    <div class="col-md-6">
        <label for="">Nombre Referencia 2</label>
        <input type="text" class="form-control" name="nombre_referencia2" value="{{ $cliente->nombre_referencia2 }}">
    </div>
    <div class="col-md-6">
        <label for="">Teléfono Referencia 2</label>
        <input type="text" class="form-control" name="telefono_referencia2" value="{{ $cliente->telefono_referencia2 }}">
    </div>
</div>
<div class="form-group">
    <label for="comentario">Comentario</label>
    <textarea name="comentario" id="comentario" class="form-control" rows="3" placeholder="Escriba un comentario...">{{ $cliente->comentario }}</textarea>
</div>



                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <a href="{{ route('admin.clientes.index') }}" class="btn btn-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-success">Modificar</button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
@stop

@section('js')
@stop
