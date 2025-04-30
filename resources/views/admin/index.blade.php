@extends('adminlte::page')

@section('content_header')
    <h1><b>Bienvenido </b></h1>
    <hr>
@stop

@section('content')
    <div class="row">

        <div class="col-md-3 col-sm-6 col-12 ">
            <div class="info-box zoomP">
                <div class="row">
                    <div class="col-md-3">
                        <a href="{{url('/admin/configuraciones')}}">
                            <img src="{{url('/images/ajustes.gif')}}" width="100%" alt="imagen">
                        </a>
                    </div>
                    <div class="col-md-9" style="margin-top: 8px">
                        <h5><b>Configuraciones registrados</b></h5>
                        {{$total_configuraciones}} configuraciones
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12 ">
            <div class="info-box zoomP">
                <div class="row">
                    <div class="col-md-3">
                        <a href="{{url('/admin/roles')}}">
                            <img src="{{url('/images/roles.gif')}}" width="100%" alt="imagen">
                        </a>
                    </div>
                    <div class="col-md-9" style="margin-top: 8px">
                        <h5><b>Roles registrados</b></h5>
                        {{$total_roles}} roles
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12 ">
            <div class="info-box zoomP">
                <div class="row">
                    <div class="col-md-3">
                        <a href="{{url('/admin/usuarios')}}">
                            <img src="{{url('/images/usuario.gif')}}" width="100%" alt="imagen">
                        </a>
                    </div>
                    <div class="col-md-9" style="margin-top: 8px">
                        <h5><b>Usuarios registrados</b></h5>
                        {{$total_usuarios}} usuarios
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12 ">
            <div class="info-box zoomP">
                <div class="row">
                    <div class="col-md-3">
                        <a href="{{url('/admin/clientes')}}">
                            <img src="{{url('/images/cliente.gif')}}" width="100%" alt="imagen">
                        </a>
                    </div>
                    <div class="col-md-9" style="margin-top: 8px">
                        <h5><b>Clientes registrados</b></h5>
                        {{$total_clientes}} clientes
                    </div>
                </div>
            </div>
        </div>

    </div>


    <div class="row">
        <div class="col-md-3 col-sm-6 col-12 ">
            <div class="info-box zoomP">
                <div class="row">
                    <div class="col-md-3">
                        <a href="{{url('/admin/prestamos')}}">
                            <img src="{{url('/images/prestamos.gif')}}" width="100%" alt="imagen">
                        </a>
                    </div>
                    <div class="col-md-9" style="margin-top: 8px">
                        <h5><b>Prestamos registrados</b></h5>
                        {{$total_prestamos}} prestamos
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12 ">
            <div class="info-box zoomP">
                <div class="row">
                    <div class="col-md-3">
                        <a href="{{url('/admin/pagos')}}">
                            <img src="{{url('/images/pagos.gif')}}" width="100%" alt="imagen">
                        </a>
                    </div>
                    <div class="col-md-9" style="margin-top: 8px">
                        <h5><b>Pagos registrados</b></h5>
                        {{$total_pagos}} pagos
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><b>Total de prestamos por mes</b></h3>
                </div>
                <div class="card-body">
                    <div>
                        <canvas id="myChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><b>Total de pagos por mes</b></h3>
                </div>
                <div class="card-body">
                    <div>
                        <canvas id="myChart2"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')

@stop

@section('js')

    @php
    $meses = array_fill(1,12,0);
    $suma_prestamos = array_fill(1,12,0);
    foreach ($prestamos as $prestamo){
        $fecha = strtotime(($prestamo['fecha_inicio']));
        $mes = date('m',$fecha);
        $meses[(int)$mes]++;
        $suma_prestamos[(int)$mes] += $prestamo['monto_prestado'];
    }
    $reporte_prestamos = implode(',',$suma_prestamos);


    $meses2 = array_fill(1,12,0);
    $suma_pagos = array_fill(1,12,0);
    foreach ($pagos as $pago){
        $fecha2 = strtotime(($pago['fecha_cancelado']));
        $mes2 = date('m',$fecha2);
        $meses2[(int)$mes2]++;
        $suma_pagos[(int)$mes2] += $pago['monto_pagado'];
    }
    $reporte_pagos = implode(',',$suma_pagos);

    @endphp

    <script>
        var meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio','Julio',
            'Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        var datos =[{{$reporte_prestamos}}];
        const ctx = document.getElementById('myChart');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: meses,
                datasets: [{
                    label: 'Total de prestamos por mes',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    data: datos,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <script>
        var meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio','Julio',
            'Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        var datos2 =[{{$reporte_pagos}}];
        const ctx2 = document.getElementById('myChart2');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: meses,
                datasets: [{
                    label: 'Total de pagos por mes',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    data: datos2,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
@stop
