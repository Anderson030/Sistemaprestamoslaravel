@extends('adminlte::page')

@section('content_header')
    <h1><b>Prestamos del cliente {{$cliente->apellidos." ".$cliente->nombres}}</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Historial de prestamos</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="example1" class="table table-bordered table-hover table-striped table-sm">
                        <thead>
                        <tr>
                            <th style="text-align: center">Nro</th>
                            <th style="text-align: center">Documento</th>
                            <th style="text-align: center">Apellidos y nombres</th>
                            <th style="text-align: center">Monto Prestado</th>
                            <th style="text-align: center">Tasa de interes</th>
                            <th style="text-align: center">Modalidad</th>
                            <th style="text-align: center">Nro de cuotas</th>
                            <th style="text-align: center">Fecha de inicio</th>
                            <th style="text-align: center">Estado del prestamo</th>
                            <th style="text-align: center">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $contador = 1;
                        @endphp
                        @foreach($prestamos as $prestamo)
                            <tr>
                                <td style="text-align: center">{{$contador++}}</td>
                                <td>{{$prestamo->cliente->nro_documento}}</td>
                                <td>{{$prestamo->cliente->apellidos." ".$prestamo->cliente->nombres}}</td>
                                <td>{{$prestamo->monto_prestado}}</td>
                                <td>{{$prestamo->tasa_interes}}</td>
                                <td>{{$prestamo->modalidad}}</td>
                                <td>{{$prestamo->nro_cuotas}}</td>
                                <td>{{$prestamo->fecha_inicio}}</td>
                                <td style="text-align: center">
                                    @if($prestamo->estado == "Pendiente")
                                        <button class="btn btn-danger">{{$prestamo->estado}}</button>
                                    @else
                                        <button class="btn btn-success">{{$prestamo->estado}}</button>
                                    @endif
                                </td>
                                <td style="text-align: center">
                                    <div class="btn-group" role="group" aria-label="Basic example">
                                        <a href="{{url('/admin/pagos/prestamos/create',$prestamo->id)}}" style="border-radius: 4px 0px 0px 4px" class="btn btn-warning btn-sm"><i class="fas fa-money-bill-wave"></i> Ver pagos</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
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
