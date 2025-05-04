@extends('adminlte::page')

@section('content_header')
    <h1><b>Pagos/Datos del pago</b></h1>
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
                                <i class="fas fa-id-card"></i> {{$cliente->nro_documento}} <br><br>
                                <i class="fas fa-user"></i> {{$cliente->apellidos." ".$cliente->nombres}} <br><br>
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

        <div class="col-md-3">
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

        <div class="col-md-5">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Datos del pago</h3>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                           <p>
                               <b>Monto Pagado: </b><i class="fas fa-money-bill-wave"></i> {{$pago->monto_pagado}} <br><br>
                               <b>Metodo de pago: </b><i class="fas fa-money-bill-wave"></i> {{$pago->metodo_pago}} <br><br>
                               <b>{{$pago->referencia_pago}} </b><br><br>
                               <b>Estado: </b>{{$pago->estado}} <br><br>
                               <b>Fecha cancelado: <i class="fas fa-calendar-alt"></i> </b>{{$pago->fecha_cancelado}}

                           </p>
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
