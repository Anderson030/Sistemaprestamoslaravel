@extends('adminlte::page')

@section('title', 'Registrar Pago Parcial')
@section('content_header')
    <h1>Registrar Pago Parcial</h1>
@stop

@section('content')
    <form action="{{ route('admin.pagos_parciales.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="cedula">Cédula del Cliente</label>
            <input type="text" name="cedula" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="nota">Nota / Observación</label>
            <textarea name="nota" rows="4" class="form-control" required></textarea>
        </div>

        <button class="btn btn-success">Guardar Nota</button>
    </form>

    <a href="{{ route('admin.pagos_parciales.index') }}" class="btn btn-secondary mt-2">Volver</a>

@stop
