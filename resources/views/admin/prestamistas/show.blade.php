@extends('adminlte::page')

@section('title', 'Detalle del Prestamista')

@section('content_header')
    <h1><b>Detalle del prestamista: {{ $usuario->name }}</b></h1>
    <hr>
@stop

@section('content')
    <div class="card">
        <div class="card-header bg-info">
            <h3 class="card-title">Préstamos realizados por {{ $usuario->name }}</h3>
        </div>
        <div class="card-body">
            @if($prestamos->isEmpty())
                <p>Este prestamista aún no ha realizado préstamos.</p>
            @else
                <table class="table table-bordered table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Monto Prestado</th>
                            <th>Monto Total</th>
                            <th>Modalidad</th>
                            <th>Cuotas</th>
                            <th>Fecha Inicio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prestamos as $prestamo)
                            <tr>
                                <td>{{ $prestamo->cliente->apellidos ?? '' }} {{ $prestamo->cliente->nombres ?? '' }}</td>
                                <td>${{ number_format($prestamo->monto_prestado, 0, ',', '.') }}</td>
                                <td>${{ number_format($prestamo->monto_total, 0, ',', '.') }}</td>
                                <td>{{ $prestamo->modalidad }}</td>
                                <td>{{ $prestamo->nro_cuotas }}</td>
                                <td>{{ $prestamo->fecha_inicio }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <div class="card mt-4">
    <div class="card-header bg-success text-white">
        <h3 class="card-title">Pagos recibidos por {{ $usuario->name }}</h3>
    </div>
    <div class="card-body">
        @if($pagos->isEmpty())
            <p>No se han registrado pagos para este prestamista.</p>
        @else
            <table class="table table-sm table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Monto Pagado</th>
                        <th>Método</th>
                        <th>Fecha de Pago</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalPagado = 0; @endphp
                    @foreach($pagos as $pago)
                        @php $totalPagado += $pago->monto_pagado; @endphp
                        <tr>
                            <td>{{ $pago->prestamo->cliente->apellidos ?? '' }} {{ $pago->prestamo->cliente->nombres ?? '' }}</td>
                            <td>${{ number_format($pago->monto_pagado, 0, ',', '.') }}</td>
                            <td>{{ $pago->metodo_pago }}</td>
                            <td>{{ $pago->fecha_pago }}</td>
                            <td>{{ $pago->estado }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><b>Total recibido</b></td>
                        <td colspan="4"><b>${{ number_format($totalPagado, 0, ',', '.') }}</b></td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
</div>
</div>
</div>
@stop
