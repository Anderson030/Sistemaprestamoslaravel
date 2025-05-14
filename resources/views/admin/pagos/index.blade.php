@extends('adminlte::page')

@section('content_header')
    <h1><b>Listado de pagos </b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Pagos registrados</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example1" class="table table-bordered table-hover table-striped table-sm">
                            <thead>
                            <tr>
                                <th style="text-align: center">Nro</th>
                                <th style="text-align: center">Documento</th>
                                <th style="text-align: center">Cliente</th>
                                <th style="text-align: center">Couta Pagada</th>
                                <th style="text-align: center">Nro de cuotas</th>
                                <th style="text-align: center">Fecha cancelado</th>
                                <th style="text-align: center">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php $contador = 1; @endphp
                            @foreach($pagos as $pago)
                                @if(!$pago->fecha_cancelado == null)
                                    <tr>
                                        <td style="text-align: center">{{$contador++}}</td>
                                        <td style="text-align: center">{{$pago->prestamo->cliente->nro_documento}}</td>
                                        <td>{{$pago->prestamo->cliente->apellidos." ".$pago->prestamo->cliente->nombres}}</td>
                                        <td style="text-align: center">{{$pago->monto_pagado}}</td>
                                        <td style="text-align: center">{{$pago->referencia_pago}}</td>
                                        <td style="text-align: center">{{$pago->fecha_cancelado}}</td>
                                        <td style="text-align: center">
                                            <div class="btn-group" role="group">
                                                <a href="{{url('/admin/pagos',$pago->id)}}" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a>
                                                <a href="{{url('/admin/pagos/comprobantedepago',$pago->id)}}" class="btn btn-warning btn-sm"><i class="fas fa-print"></i></a>
                                                <form action="{{url('/admin/pagos',$pago->id)}}" method="post" id="miFormulario{{$pago->id}}" onclick="preguntar{{$pago->id}}(event)">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                </form>
                                                <script>
                                                    function preguntar{{$pago->id}}(event) {
                                                        event.preventDefault();
                                                        Swal.fire({
                                                            title: '¿Desea eliminar esta registro?',
                                                            icon: 'question',
                                                            showDenyButton: true,
                                                            confirmButtonText: 'Eliminar',
                                                            confirmButtonColor: '#a5161d',
                                                            denyButtonColor: '#270a0a',
                                                            denyButtonText: 'Cancelar',
                                                        }).then((result) => {
                                                            if (result.isConfirmed) {
                                                                document.getElementById('miFormulario{{$pago->id}}').submit();
                                                            }
                                                        });
                                                    }
                                                </script>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Busqueda del cliente</h3>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        </div>
                        <select name="cliente_id" class="form-control select2">
                            <option value="">Buscar cliente...</option>
                            @foreach($clientes as $cliente)
                                <option value="{{$cliente->id}}">{{$cliente->nro_documento." - ".$cliente->apellidos." ".$cliente->nombres}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        #example1_wrapper .dt-buttons {
            background-color: transparent;
            box-shadow: none;
            border: none;
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            justify-content: flex-start;
            gap: 10px;
            margin-bottom: 15px;
        }
        #example1_wrapper .btn {
            color: #fff;
            border-radius: 4px;
            padding: 5px 15px;
            font-size: 14px;
            white-space: nowrap;
        }
        .btn-danger { background-color: #dc3545; border: none; }
        .btn-success { background-color: #28a745; border: none; }
        .btn-info { background-color: #17a2b8; border: none; }
        .btn-warning { background-color: #ffc107; color: #212529; border: none; }
        .btn-default { background-color: #6e7176; color: #212529; border: none; }
        .select2-container .select2-selection--single {
            height: 40px !important;
        }
    </style>
@stop

@section('js')
    <script>
        $('.select2').select2({});

        $('.select2').on('change', function () {
            var id = $(this).val();
            if (id) {
                window.location.href = "{{url('/admin/pagos/prestamos/cliente/')}}" + "/" + id;
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
