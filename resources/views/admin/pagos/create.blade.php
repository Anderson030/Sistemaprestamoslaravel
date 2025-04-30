@extends('adminlte::page')

@section('content_header')
    <h1><b>Pagos/Registro de un nuevo pago</b></h1>
    <hr>
@stop

@section('content')

        <div class="row">
            <div class="col-md-4">
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Datos del cliente</h3>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p>
                                    <i class="fas fa-id-card"></i> {{$prestamo->cliente->nro_documento}} <br><br>
                                    <i class="fas fa-user"></i> {{$prestamo->cliente->apellidos." ".$prestamo->cliente->nombres}} <br><br>
                                    <i class="fas fa-calendar"></i> {{$prestamo->cliente->fecha_nacimiento}} <br><br>
                                    <i class="fas fa-user-check"></i> {{$prestamo->cliente->genero}} <br><br>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p>
                                    <i class="fas fa-envelope"></i> {{$prestamo->cliente->email}} <br><br>
                                    <i class="fas fa-phone"></i> {{$prestamo->cliente->celular}} <br><br>
                                    <i class="fas fa-phone"></i> {{$prestamo->cliente->ref_celular}} <br><br>
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>

            <div class="col-md-2">
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Datos del prestamo</h3>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p>
                                    <b>Monto prestado</b> <br>
                                    <i class="fas fa-money-bill-wave"></i> {{$prestamo->monto_prestado}} <br><br>

                                    <b>Tasa de interes</b> <br>
                                    <i class="fas fa-percentage"></i> {{$prestamo->tasa_interes}} <br><br>

                                    <b>Modalidad</b> <br>
                                    <i class="fas fa-calendar-alt"></i> {{$prestamo->modalidad}} <br><br>

                                    <b>Nro de cuotas</b> <br>
                                    <i class="fas fa-list"></i> {{$prestamo->nro_cuotas}} cuotas<br><br>

                                    <b>Monto Total</b> <br>
                                    <i class="fas fa-money-bill-alt"></i> {{$prestamo->monto_total}}<br><br>
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>


            <div class="col-md-6">
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Datos de los pagos</h3>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <table class="table table-sm table-striped table-hover table-bordered">
                                    <thead>
                                    <tr>
                                        <th style="text-align: center">Nro de cuota</th>
                                        <th style="text-align: center">Monto de la cuota</th>
                                        <th style="text-align: center">Fecha de pago</th>
                                        <th style="text-align: center">Estado</th>
                                        <th style="text-align: center">Fecha cancelado</th>
                                        <th style="text-align: center">Acción</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $contador = 1;
                                    @endphp
                                    @foreach($pagos as $pago)
                                        <tr>
                                            <td style="text-align: center">{{$contador++}}</td>
                                            <td style="text-align: center">{{$pago->monto_pagado}}</td>
                                            <td style="text-align: center">{{$pago->fecha_pago}}</td>
                                            <td style="text-align: center">{{$pago->estado}}</td>
                                            <td style="text-align: center">{{$pago->fecha_cancelado}}</td>
                                            <td>
                                                @if($pago->estado == "Confirmado")
                                                    <button type="button" class="btn btn-danger btn-sm">Cancelado</button>
                                                    <a href="{{url('/admin/pagos/comprobantedepago',$pago->id)}}" class="btn btn-warning btn-sm"><i class="fas fa-print"></i> Imprimir</a>
                                                @else
                                                    <form action="{{url('/admin/pagos/create',$pago->id)}}" method="post"
                                                          onclick="preguntar{{$pago->id}}(event)" id="miFormulario{{$pago->id}}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm" style="border-radius: 4px 4px 4px 4px"><i class="fas fa-money-bill"></i> Pagar</button>
                                                    </form>
                                                    <script>
                                                        function preguntar{{$pago->id}}(event) {
                                                            event.preventDefault();
                                                            Swal.fire({
                                                                title: '¿Esta seguro de registrar el pago?',
                                                                text: '',
                                                                icon: 'question',
                                                                showDenyButton: true,
                                                                confirmButtonText: 'Si',
                                                                confirmButtonColor: '#a5161d',
                                                                denyButtonColor: '#270a0a',
                                                                denyButtonText: 'Cancelar',
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    var form = $('#miFormulario{{$pago->id}}');
                                                                    form.submit();
                                                                }
                                                            });
                                                        }
                                                    </script>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
