@extends('adminlte::page')

@section('content_header')
    <h1><b>Notificaciones/Listado de pagos </b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Pagos pendientes</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="example1" class="table table-bordered table-hover table-striped table-sm">
                        <thead>
                        <tr>
                            <th style="text-align: center">Nro</th>
                            <th style="text-align: center">Documento</th>
                            <th style="text-align: center">Cliente</th>
                            <th style="text-align: center">Correo</th>
                            <th style="text-align: center">Celular</th>
                            <th style="text-align: center">Referencia Celular</th>
                            <th style="text-align: center">Couta Pagada</th>
                            <th style="text-align: center">Nro de cuotas</th>
                            <th style="text-align: center">Fecha de pago</th>
                            <th style="text-align: center">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $contador = 1;
                        @endphp
                        @foreach($pagos as $pago)
                            @if($pago->fecha_cancelado == null)
                                <tr>
                                    <td style="text-align: center">{{$contador++}}</td>
                                    <td style="text-align: center">{{$pago->prestamo->cliente->nro_documento}}</td>
                                    <td>{{$pago->prestamo->cliente->apellidos." ".$pago->prestamo->cliente->nombres}}</td>
                                    <td style="text-align: center">{{$pago->prestamo->cliente->email}}</td>
                                    <td style="text-align: center">{{$pago->prestamo->cliente->celular}}</td>
                                    <td style="text-align: center">{{$pago->prestamo->cliente->ref_celular}}</td>
                                    <td style="text-align: center">{{$pago->monto_pagado}}</td>
                                    <td style="text-align: center">{{$pago->referencia_pago}}</td>
                                    @if($pago->fecha_pago == date('Y-m-d'))
                                        <td style="text-align: center;background-color: #f7f61e">{{$pago->fecha_pago}}</td>
                                    @elseif($pago->fecha_pago  < date('Y-m-d'))
                                        <td style="text-align: center;background-color: #f77c7b">{{$pago->fecha_pago}}</td>
                                    @else
                                    <td style="text-align: center">{{$pago->fecha_pago}}</td>
                                    @endif
                                    <td style="text-align: center">
                                        <div class="btn-group" role="group" aria-label="Basic example">
                                            <a href="https://wa.me/{{$pago->prestamo->cliente->celular}}?text=Hola cliente, {{$pago->prestamo->cliente->apellidos." ".$pago->prestamo->cliente->nombres}}, usted tiene  una cuota atrasada, por favor realize el pago lo más antes posible. Atte: {{$configuracion->nombre}}"
                                               target="_blank" class="btn btn-success btn-sm"><i class="fas fa-phone"></i> Celular</a>
                                            <a href="{{url('/admin/notificaciones/notificar',$pago->id)}}" class="btn btn-info btn-sm"><i class="fas fa-envelope"></i> Enviar correo</a>
                                        </div>
                                    </td>
                                </tr>
                            @endif
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
    <style>
        /* Fondo transparente y sin borde en el contenedor */
        #example1_wrapper .dt-buttons {
            background-color: transparent;
            box-shadow: none;
            border: none;
            display: flex;
            justify-content: center; /* Centrar los botones */
            gap: 10px; /* Espaciado entre botones */
            margin-bottom: 15px; /* Separar botones de la tabla */
        }

        /* Estilo personalizado para los botones */
        #example1_wrapper .btn {
            color: #fff; /* Color del texto en blanco */
            border-radius: 4px; /* Bordes redondeados */
            padding: 5px 15px; /* Espaciado interno */
            font-size: 14px; /* Tamaño de fuente */
        }

        /* Colores por tipo de botón */
        .btn-danger { background-color: #dc3545; border: none; }
        .btn-success { background-color: #28a745; border: none; }
        .btn-info { background-color: #17a2b8; border: none; }
        .btn-warning { background-color: #ffc107; color: #212529; border: none; }
        .btn-default { background-color: #6e7176; color: #212529; border: none; }

        .select2-container .select2-selection--single {
            height: 40px !important; /* Ajusta la altura total del select */
        }
    </style>
@stop

@section('js')
    <script>
        $('.select2').select2({});

        $('.select2').on('change',function () {
            var id = $(this).val();
            //alert(cliente_id);
            if(id){
                window.location.href ="{{url('/admin/pagos/prestamos/cliente/')}}"+"/"+id;
            }
        });

        $(function () {
            $("#example1").DataTable({
                "pageLength": 5,
                "language": {
                    "emptyTable": "No hay información",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ Pagos",
                    "infoEmpty": "Mostrando 0 a 0 de 0 Pagos",
                    "infoFiltered": "(Filtrado de _MAX_ total Pagos)",
                    "lengthMenu": "Mostrar _MENU_ Pagos",
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                    "search": "Buscador:",
                    "zeroRecords": "Sin resultados encontrados",
                    "paginate": {
                        "first": "Primero",
                        "last": "Último",
                        "next": "Siguiente",
                        "previous": "Anterior"
                    }
                },
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                buttons: [
                    { text: '<i class="fas fa-copy"></i> COPIAR', extend: 'copy', className: 'btn btn-default' },
                    { text: '<i class="fas fa-file-pdf"></i> PDF', extend: 'pdf', className: 'btn btn-danger' },
                    { text: '<i class="fas fa-file-csv"></i> CSV', extend: 'csv', className: 'btn btn-info' },
                    { text: '<i class="fas fa-file-excel"></i> EXCEL', extend: 'excel', className: 'btn btn-success' },
                    { text: '<i class="fas fa-print"></i> IMPRIMIR', extend: 'print', className: 'btn btn-warning' }
                ]
            }).buttons().container().appendTo('#example1_wrapper .row:eq(0)');
        });
    </script>
@stop
